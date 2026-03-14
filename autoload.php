<?php
/**
 * AdlairePlatform - Framework オートローダー
 *
 * Framework/ モジュールの名前空間を自動解決する。
 * engines/ は従来通り require で読み込む。
 *
 * @since Ver.1.5.0
 * @license Adlaire License Ver.2.0
 */

spl_autoload_register(function (string $class): void {

    /*
     * 名前空間 → ファイルのマッピング
     *
     * 各 Framework モジュールは 1 ファイルに複数クラスを格納する設計のため、
     * PSR-4 のような 1 クラス = 1 ファイル方式ではなく、
     * 名前空間プレフィックス → 物理ファイルの直接マッピングを使用する。
     */
    static $map = [
        /* APF - Adlaire Platform Foundation */
        'APF\\Core\\'       => 'Framework/APF/APF.Core.php',
        'APF\\Database\\'   => 'Framework/APF/APF.Database.php',
        'APF\\Utilities\\'  => 'Framework/APF/APF.Utilities.php',

        /* ACE - Adlaire CMS Engine */
        'ACE\\Core\\'       => 'Framework/ACE/ACE.Core.php',
        'ACE\\Admin\\'      => 'Framework/ACE/ACE.Admin.php',
        'ACE\\Api\\'        => 'Framework/ACE/ACE.Api.php',

        /* AIS - Adlaire Infrastructure Services */
        'AIS\\Core\\'       => 'Framework/AIS/AIS.Core.php',
        'AIS\\System\\'     => 'Framework/AIS/AIS.System.php',
        'AIS\\Deployment\\' => 'Framework/AIS/AIS.Deployment.php',

        /* ASG - Adlaire Static Generator */
        'ASG\\Core\\'       => 'Framework/ASG/ASG.Core.php',
        'ASG\\Template\\'   => 'Framework/ASG/ASG.Template.php',
        'ASG\\Utilities\\'  => 'Framework/ASG/ASG.Utilities.php',
    ];

    /* 読み込み済みファイルの追跡（同一ファイルの二重 require を防止） */
    static $loaded = [];

    foreach ($map as $prefix => $file) {
        if (str_starts_with($class, $prefix)) {
            if (!isset($loaded[$file])) {
                $path = __DIR__ . '/' . $file;
                if (is_file($path)) {
                    require $path;
                    $loaded[$file] = true;
                }
            }
            return;
        }
    }
});
