# Exchange rates

UnoPIM stores exchange rates as `1 CNY = N target currency` for `USD`, `MYR`, and `THB`.

## Update rates

Frankfurter rates are checked every three hours by the Laravel scheduler. A manual update is available at `/admin/settings/exchange-rates`.

The management page shows the exact update time, Chinese currency names, and linked CNY/USD/MYR/THB inputs. Enter an amount in any currency to recalculate every other currency immediately.

```bash
php artisan unopim:exchange-rates:refresh --force
```

If the latest response is incomplete, UnoPIM requests the previous one-day period and averages it. If that also fails, it uses locally stored records fetched during the previous day. Records older than 31 days are removed.

The scheduler requires the normal Laravel scheduler worker or cron entry to be running.

## Rate types

- Real rate: the latest Frankfurter rate.
- Selling rate: an optional value maintained by an administrator. It defaults to the real rate.

Product imports retain the original CNY price and calculate USD, MYR, and THB prices with the selling rate. The authenticated endpoint `GET /api/v1/rest/exchange-rates` provides real, selling, and cross-currency matrices.

## Configuration

| Variable | Default |
| --- | --- |
| `FRANKFURTER_URL` | `https://api.frankfurter.dev/v2/rates` |
| `EXCHANGE_RATE_UPDATE_HOURS` | `3` |
| `EXCHANGE_RATE_CURRENCIES` | `USD,MYR,THB` |
| `EXCHANGE_RATE_CA_BUNDLE` | Composer CA bundle when empty |
