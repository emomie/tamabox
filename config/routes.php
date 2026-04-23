<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/*
 * This file is loaded in the context of the `Application` class.
  * So you can use  `$this` to reference the application class instance
  * if required.
 */
return function (RouteBuilder $routes): void {
    /*
     * The default class to use for all routes
     *
     * The following route classes are supplied with CakePHP and are appropriate
     * to set as the default:
     *
     * - Route
     * - InflectedRoute
     * - DashedRoute
     *
     * If no call is made to `Router::defaultRouteClass()`, the class used is
     * `Route` (`Cake\Routing\Route\Route`)
     *
     * Note that `Route` does not do any inflections on URLs which will result in
     * inconsistently cased URLs when used with `{plugin}`, `{controller}` and
     * `{action}` markers.
     */
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/', function (RouteBuilder $builder): void {
        /*
         * Here, we are connecting '/' (base path) to a controller called 'Pages',
         * its action called 'display', and we pass a param to select the view file
         * to use (in this case, templates/Pages/home.php)...
         */
        $builder->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);

        /*
         * ...and connect the rest of 'Pages' controller's URLs.
         */
        $builder->connect('/pages/*', 'Pages::display');

        /*
         * Phase 2 — Bluesky OAuth routes.
         * See UI-SPEC.md §1 for route/method/auth-required mapping.
         * Controller classes (AuthController, OauthController, UsersController)
         * are introduced in Plans 02-03 and 02-04; route definitions are valid now.
         */
        // GET (future login-start page) / POST (CTA form submit starts PAR flow).
        $builder->connect('/login/bluesky', ['controller' => 'Auth', 'action' => 'startBluesky'])
            ->setMethods(['GET', 'POST']);

        // GET only — Bluesky AS redirects here with code/state/iss.
        $builder->connect('/oauth/callback', ['controller' => 'Oauth', 'action' => 'callback'])
            ->setMethods(['GET']);

        // GET only — Bluesky AS polls this as client_id. Must return application/json verbatim.
        $builder->connect('/oauth/client-metadata.json', ['controller' => 'Oauth', 'action' => 'clientMetadata'])
            ->setMethods(['GET']);

        // GET only — public JWKS for AS signature verification.
        $builder->connect('/oauth/jwks.json', ['controller' => 'Oauth', 'action' => 'jwks'])
            ->setMethods(['GET']);

        // POST only — CSRF-protected logout (D-18).
        $builder->connect('/oauth/logout', ['controller' => 'Auth', 'action' => 'logout'])
            ->setMethods(['POST']);

        // GET only — authenticated landing (placeholder for Plan 02-04; Phase 3 adds inbox).
        $builder->connect('/dashboard', ['controller' => 'Users', 'action' => 'dashboard'])
            ->setMethods(['GET']);

        /*
         * Connect catchall routes for all controllers.
         *
         * The `fallbacks` method is a shortcut for
         *
         * ```
         * $builder->connect('/{controller}', ['action' => 'index']);
         * $builder->connect('/{controller}/{action}/*', []);
         * ```
         *
         * You can remove these routes once you've connected the
         * routes you want in your application.
         */
        $builder->fallbacks();
    });

    /*
     * If you need a different set of middleware or none at all,
     * open new scope and define routes there.
     *
     * ```
     * $routes->scope('/api', function (RouteBuilder $builder): void {
     *     // No $builder->applyMiddleware() here.
     *
     *     // Parse specified extensions from URLs
     *     // $builder->setExtensions(['json', 'xml']);
     *
     *     // Connect API actions here.
     * });
     * ```
     */
};
