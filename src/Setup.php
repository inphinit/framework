<?php
/*
 * Inphinit
 *
 * Copyright (c) 2021 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

/**
 * Generate .htaccess
 *
 * @param string $base Define path equivalent "HTTP path"
 * @return void
 */
function SetupApache($base)
{
    if (
        empty($_SERVER['SERVER_SOFTWARE']) ||
        stripos($_SERVER['SERVER_SOFTWARE'], 'apache') === false
    ) {
        echo 'Warning: Use this script only with Apache', PHP_EOL;
        exit;
    }

    $base = dirname($_SERVER['PHP_SELF']);
    $base = rtrim(strtr($base, '\\', '/'), '/') . '/index.php/RESERVED.INPHINIT-';

    $data = '<IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    IndexIgnore *

    # Redirect page errors to route system
    ErrorDocument 401 ' . $base . '401.html
    ErrorDocument 403 ' . $base . '403.html
    ErrorDocument 500 ' . $base . '500.html
    ErrorDocument 501 ' . $base . '501.html

    RewriteEngine On

    # Disable access to folder and redirect ./system/* path to "routes"
    RewriteRule ^(system/|system$) index.php [L]

    # Check file or folders exists
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f

    # Redirect all urls to index.php if no exits files/folder
    RewriteRule ^ index.php [L]
    ';

    $data = str_replace("\n    ", "\n", $data);

    file_put_contents('.htaccess', $data);

    echo '<pre>', htmlspecialchars($data), '</pre>';
}

/**
 * Generate web.config for IIS
 *
 * @param string $base Define path equivalent "HTTP path"
 * @return void
 */
function SetupIIS($base)
{
    if (
        empty($_SERVER['SERVER_SOFTWARE']) ||
        stripos($_SERVER['SERVER_SOFTWARE'], 'microsoft-iis') === false
    ) {
        echo 'Warning: Use this script only with IIS or IIS Express', PHP_EOL;
        exit;
    }

    $base = rtrim(strtr($base, '\\', '/'), '/') . '/index.php/RESERVED.INPHINIT-';

    $data = '<?xml version="1.0" encoding="UTF-8"?>
    <configuration>
        <system.webServer>
            <defaultDocument>
                <files>
                    <clear />
                    <add value="index.php" />
                </files>
            </defaultDocument>
            <httpErrors>
                <remove statusCode="401" subStatusCode="-1" />
                <remove statusCode="403" subStatusCode="-1" />
                <remove statusCode="500" subStatusCode="-1" />
                <remove statusCode="501" subStatusCode="-1" />
                <error statusCode="401"
                       responseMode="ExecuteURL"
                       path="' . $base . '401.html?RESERVED_IISREDIRECT=1" />
                <error statusCode="403"
                       responseMode="ExecuteURL"
                       path="' . $base . '403.html?RESERVED_IISREDIRECT=1" />
                <error statusCode="501"
                       responseMode="ExecuteURL"
                       path="' . $base . '501.html?RESERVED_IISREDIRECT=1" />
            </httpErrors>
            <rewrite>
                <rules>
                    <rule name="Ignore system folder" stopProcessing="true">
                        <match url="^(system/|system$)" ignoreCase="false" />
                        <action type="Rewrite" url="index.php" />
                    </rule>
                    <rule name="Redirect to routes" stopProcessing="true">
                        <conditions>
                            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                        </conditions>
                        <match url="^" ignoreCase="false" />
                        <action type="Rewrite" url="index.php" />
                    </rule>
                </rules>
            </rewrite>
        </system.webServer>
    </configuration>
    ';

    $data = str_replace("\n    ", "\n", $data);

    file_put_contents('web.config', $data);

    echo '<pre>', htmlspecialchars($data), '</pre>';
}

/**
 * Generate configs to nginx.conf and setup extensions used (eg. hh for HHVM)
 *
 * @param string $base       Define full path
 * @param array  $extensions Define extensions used by server
 * @return void
 */
function SetupNginx($base, array $extensions)
{
    $base = rtrim(strtr($base, '\\', '/'), '/');

    $exts = implode('|', $extensions);

    if (count($extensions) > 1) {
        $exts = '(' . $exts . ')';
    }

    $data = '
    root "' . $base . '/";

    # Disable access to folder and redirect ./system/* path to "routes"
    location ~ ^/(system/|system$) {
        rewrite ^ /index.php last;
    }

    location / {
        autoindex on;

        index  index.html index.htm index.php;

        error_page 403 /index.php/RESERVED.INPHINIT-403.html;
        error_page 404 /index.php/RESERVED.INPHINIT-404.html;
        error_page 500 /index.php/RESERVED.INPHINIT-500.html;
        error_page 501 /index.php/RESERVED.INPHINIT-501.html;

        try_files $uri $uri/ /index.php?$query_string;
    }

    # Option, your server may have already been configured
    location ~ \.' . $exts . '$ {
        fastcgi_pass   127.0.0.1:9000; # Replace by your fastcgi
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }
    ';

    $data = str_replace("\n    ", "\n", $data);

    if (PHP_SAPI !== 'cli') {
        echo '<pre>', htmlspecialchars($data), '</pre>';
    } else {
        echo $data;
    }
}

/**
 * Generate server.bat or server.sh
 *
 * @param string $defaultHost
 * @param string $defaultPort
 * @return void
 */
function SetupBuiltIn($defaultHost = 'localhost', $defaultPort = '9000')
{
    if (PHP_SAPI !== 'cli') {
        echo 'Warning: Use this script only with CLI', PHP_EOL;
        exit;
    } elseif (defined('PHP_BINARY') === false) {
        echo 'Warning: versions older than PHP 5.4 don\'t support "Built-in web server", for PHP 5.3.x use Apache or Nginx', PHP_EOL;
        return;
    }

    $php = PHP_BINARY;
    $ini = php_ini_loaded_file();
    $windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    if ($windows) {
        $data = '@echo off

        :: Setup PHP and PORT
        set PHP_BIN="' . $php . '"
        set PHP_INI="' . $ini . '"
        set HOST_HOST=' . $defaultHost . '
        set HOST_PORT=' . $defaultPort . '

        :: Current path
        set CURRENT_PATH=%~dp0
        set CURRENT_PATH=%CURRENT_PATH:~0,-1%

        if not exist %PHP_BIN% (
            echo.
            echo ERROR: %PHP_BIN% not found
            echo.
        ) else if not exist %PHP_INI% (
            echo.
            echo ERROR: %PHP_INI% not found
            echo.
        ) else (
            :: Router path
            set ROUTER="%CURRENT_PATH%\system\boot\server.php"

            :: Start built in server
            %PHP_BIN% -S "%HOST_HOST%:%HOST_PORT%" -c %PHP_INI% -t "%CURRENT_PATH%" %ROUTER%
        )

        :: Prevent close if PHP failed to start
        pause';
    } else {
        $data = '#!/bin/bash

        # Setup PHP and PORT
        PHP_BIN="' . $php . '"
        PHP_INI="' . $ini . '"
        HOST_HOST=' . $defaultHost . '
        HOST_PORT=' . $defaultPort . '

        # Used to restore current dir if using command line
        CURRENT_PATH=$(dirname "${0}")

        if [ ! -f "$PHP_BIN" ]; then
            echo "ERROR: $PHP_BIN not found"
        elif [ ! -f "$PHP_INI" ]; then
            echo "ERROR: $PHP_INI not found"
        else
            # Router path
            ROUTER="$CURRENT_PATH/system/boot/server.php"

            # Start built in server
            $PHP_BIN -S "$HOST_HOST:$HOST_PORT" -c $PHP_INI -t "$CURRENT_PATH" $ROUTER
        fi';
    }

    $data = str_replace("\n        ", "\n", $data);
    $script = 'server.sh';

    if ($windows) {
        $script = 'server.bat';
        $data = str_replace("\n", "\r\n", $data);
    }

    file_put_contents($script, $data);
}
