<?php

namespace ISLE;

class Settings
{
    public const FILE = __DIR__ . '/../settings.php';
    public const MAJOR_VERSION = 2;
    public const MINOR_VERSION = 0;
    public const PATCH_VERSION = 0;

    protected static $settings;

    public static function get($key = NULL)
    {
        // Load from file once
        if (!is_array(static::$settings)) {
            // Default values
            $web_root = rtrim(strtok($_SERVER['REQUEST_URI'], '?'), '/');
            static::$settings = [
                'autocomplete_limit' => 10,
                'footer' => [
                    'center' => '<a herf="https://github.com/nasa/isle/issues" target="_blank" class="btn link-secondary p-0">Got Feedback?</a>',
                    'end' => '<a href="https://github.com/nasa/isle/wiki" target="_blank" class="btn link-secondary p-0">Help<i class="bi-question-circle ms-2"></i></a>',
                    'start' => '<a href="https://github.com/nasa/isle" target="_blank" class="btn link-secondary p-0">ISLE ' . static::version() . '</a>'
                ],
                'hooks' => [
                    'authentication' => 'ISLE\Service::userAuthenticator',
                    'menu' => function() {},
                    'pageend' => function() {}
                ],
                'logo' => '',
                'mime_types' => ['application/pdf'],
                'results_per_page' => 50,
                'table_prefix' => '',
                'title' => 'ISLE',
                'uploads_path' => $_SERVER['DOCUMENT_ROOT'] . $web_root . '/uploads',
                'web_root' => $web_root // For inital setup only
            ];
            if (file_exists(static::FILE)) {
                static::$settings = array_replace_recursive(
                    static::$settings,
                    require_once static::FILE
                );
            }
        }
        if (!is_null($key)) {
            return static::$settings[$key] ?? null;
        }
        return static::$settings;
    }

    public static function version()
    {
        $commitHash = trim(exec('git log --pretty="%h" -n1 HEAD'));
        if ($commitHash) {
            $commitDate = new \DateTime(trim(exec('git log -n1 --pretty=%ci HEAD')));
            $commitDate->setTimezone(new \DateTimeZone('UTC'));
            $commitString = sprintf('-dev.%s (%s)', $commitHash, $commitDate->format('Y-m-d H:i:s'));
        }

        return sprintf(
            'v%s.%s.%s%s',
            static::MAJOR_VERSION,
            static::MINOR_VERSION,
            static::PATCH_VERSION,
            $commitString ?? ''
        );
    }
}
