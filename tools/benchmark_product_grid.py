"""Bounded authenticated UnoPIM product-grid benchmark.

Every sample uses a separate opener so client-side connection/session sharing
cannot hide server queueing. The script never starts or stops application
services; it exits non-zero if any measured response is not 2xx.
"""

import argparse
import concurrent.futures
import json
import math
import time
import urllib.request
from pathlib import Path


def percentile(values, ratio):
    return values[max(0, math.ceil(len(values) * ratio) - 1)]


def request_once(url, cookie, timeout):
    request = urllib.request.Request(
        url,
        headers={
            "Accept": "application/json, text/plain, */*",
            "Cookie": cookie,
            "X-Requested-With": "XMLHttpRequest",
        },
    )
    started = time.perf_counter()

    try:
        with urllib.request.build_opener(urllib.request.ProxyHandler({})).open(request, timeout=timeout) as response:
            body = response.read()
            return {
                "status": response.status,
                "ms": round((time.perf_counter() - started) * 1000, 2),
                "bytes": len(body),
                "error": None,
            }
    except Exception as error:  # benchmark output must retain failed samples
        return {
            "status": getattr(error, "code", 0),
            "ms": round((time.perf_counter() - started) * 1000, 2),
            "bytes": 0,
            "error": str(error),
        }


def measure(url, cookie, timeout, concurrency, count):
    started = time.perf_counter()
    with concurrent.futures.ThreadPoolExecutor(max_workers=concurrency) as pool:
        rows = list(pool.map(lambda _: request_once(url, cookie, timeout), range(count)))
    elapsed = time.perf_counter() - started
    successful = [row for row in rows if 200 <= row["status"] < 300]
    latencies = sorted(row["ms"] for row in successful)

    summary = {
        "concurrency": concurrency,
        "count": count,
        "success": len(successful),
        "errors": count - len(successful),
        "throughput_rps": round(len(successful) / elapsed, 2),
        "status_counts": {},
        "samples": rows,
    }
    for row in rows:
        key = str(row["status"])
        summary["status_counts"][key] = summary["status_counts"].get(key, 0) + 1
    if latencies:
        summary.update({
            "p50_ms": percentile(latencies, 0.50),
            "p95_ms": percentile(latencies, 0.95),
            "p99_ms": percentile(latencies, 0.99),
        })

    return summary


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--url", required=True, help="Full product-grid AJAX URL")
    parser.add_argument("--cookie-file", type=Path, required=True, help="UTF-8 file containing the Cookie header value")
    parser.add_argument("--concurrency", type=int, nargs="+", default=[1, 4, 8, 16])
    parser.add_argument("--count", type=int, default=16)
    parser.add_argument("--warmup", type=int, default=3)
    parser.add_argument("--timeout", type=float, default=20)
    parser.add_argument("--output", type=Path)
    args = parser.parse_args()

    if args.count < 1 or args.count > 200 or any(level not in (1, 4, 8, 16) for level in args.concurrency):
        parser.error("count must be 1..200 and concurrency must use 1, 4, 8, or 16")

    cookie = args.cookie_file.read_text(encoding="utf-8").strip()
    for _ in range(args.warmup):
        request_once(args.url, cookie, args.timeout)

    report = {
        "url": args.url,
        "independent_sessions": True,
        "warmup": args.warmup,
        "measurements": [
            measure(args.url, cookie, args.timeout, level, args.count)
            for level in args.concurrency
        ],
    }
    rendered = json.dumps(report, ensure_ascii=False, indent=2)
    print(rendered)
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(rendered, encoding="utf-8")

    raise SystemExit(1 if any(item["errors"] for item in report["measurements"]) else 0)


if __name__ == "__main__":
    main()
