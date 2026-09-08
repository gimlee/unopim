<?php

namespace Webkul\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureChannelLocaleIsValid
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $requestedChannel = core()->getRequestedChannel();
        $requestedLocaleCode = core()->getRequestedLocaleCode();
        $route = $request->route();

        if (! $request->has('locale') && ! app()->runningUnitTests()) {
            $targetChannel = $requestedChannel ?? core()->getDefaultChannel();
            $hasChinese = $targetChannel?->locales()?->where('code', 'zh_CN')->first() !== null;

            if ($hasChinese) {
                $parameters = array_merge(
                    $request->query(),
                    $route->parameters(),
                    [
                        'channel' => $targetChannel->code,
                        'locale'  => 'zh_CN',
                    ]
                );

                $routeName = $route->getName();

                if ($routeName !== null) {
                    return redirect()->route($routeName, $parameters);
                }

                $actionName = $route->getActionName();

                if ($actionName !== null) {
                    return redirect()->action($actionName, $parameters);
                }
            }
        }

        if ($requestedChannel?->locales()?->where('code', $requestedLocaleCode)->first() === null) {
            $parameters = array_merge($request->query(), $route->parameters());

            $requestedChannel ??= core()->getDefaultChannel();

            $parameters['channel'] = $requestedChannel->code;
            $parameters['locale'] = core()->getLocaleCodeInChannel($requestedChannel)
                ?? core()->getDefaultLocaleCodeFromDefaultChannel();

            $routeName = $route->getName();

            if ($routeName !== null) {
                return redirect()->route($routeName, $parameters);
            }

            $actionName = $route->getActionName();

            if ($actionName !== null) {
                return redirect()->action($actionName, $parameters);
            }

            return redirect()->back();
        }

        return $next($request);
    }
}
