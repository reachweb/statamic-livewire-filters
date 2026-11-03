<?php

namespace Reach\StatamicLivewireFilters\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Reach\StatamicLivewireFilters\Support\CustomQueryString;
use Statamic\Facades\Data;
use Statamic\Http\Controllers\FrontendController;
use Statamic\StaticCaching\NoCache\Session;

/**
 * Serves custom query string URLs (`/cars/filters/brand/audi`) as the base page
 * (`/cars`) without rewriting the request, so the static cache keys on the pretty URL.
 */
class FiltersController extends FrontendController
{
    public function __construct()
    {
        // Runs before statamic.web, so a static cache hit renders its nocache
        // regions with the params too.
        $this->middleware(function (Request $request, $next) {
            if (! Data::findByRequestUrl($request->url()) && ($split = CustomQueryString::split($request->path()))) {
                [$base, $segments] = $split;

                $params = CustomQueryString::parseSegments($segments);
                $request->query->set('params', $params);
                $request->merge(['params' => $params]);

                // Keep the nocache session on the base page, as the /!/nocache
                // endpoint restores it (and its cascade) from the base URL.
                $session = app(Session::class);
                $session->setUrl(Str::replaceFirst($request->url(), $this->baseUrl($request, $base), $session->url()));
            }

            return $next($request);
        });

        parent::__construct();
    }

    public function index(Request $request)
    {
        // A real page whose URL contains the prefix segment wins over filters.
        if ($data = Data::findByRequestUrl($request->url())) {
            return $data;
        }

        if (($split = CustomQueryString::split($request->path()))
            && ($data = Data::findByRequestUrl($this->baseUrl($request, $split[0])))) {
            return $data;
        }

        return parent::index($request);
    }

    protected function baseUrl(Request $request, string $base): string
    {
        return $request->root().'/'.$base;
    }
}
