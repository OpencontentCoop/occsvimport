<?php

class OCMProvider implements ezpRestProviderInterface
{
    public function getRoutes()
    {
        $version = 1;
        return [
            'getOCMTrasparenza' => new ezpRestVersionedRoute(new OpenApiRailsRoute(
                '/trasparenza',
                'OCMController',
                'getTrasparenza',
                [],
                'http-get'
            ), $version),
            'getOCMField' => new ezpRestVersionedRoute(new OpenApiRailsRoute(
                '/:collection/:item/:field',
                'OCMController',
                'getItemField',
                [],
                'http-get'
            ), $version),
            'getOCMItem' => new ezpRestVersionedRoute(new OpenApiRailsRoute(
                '/:collection/:item',
                'OCMController',
                'getItem',
                [],
                'http-get'
            ), $version),
            'getOCMCollection' => new ezpRestVersionedRoute(new OpenApiRailsRoute(
                '/:collection',
                'OCMController',
                'getCollection',
                [],
                'http-get'
            ), $version),
        ];
    }

    public function getViewController()
    {
        return new OCMViewController();
    }

}
