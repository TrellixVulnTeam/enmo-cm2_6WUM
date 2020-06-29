<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 */

/**
 * @brief Installer Controller
 *
 * @author dev@maarch.org
 */

namespace SrcCore\controllers;

use Respect\Validation\Validator;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\AuthenticationModel;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\DatabasePDO;

class InstallerController
{
    public function getPrerequisites(Request $request, Response $response)
    {
        $phpVersion = phpversion();
        $phpVersionValid = (version_compare(PHP_VERSION, '7.2') >= 0);

        exec('whereis unoconv', $output, $return);
        $output = explode(':', $output[0]);
        $unoconv = !empty($output[1]);

        exec('whereis netcat', $outputNetcat, $returnNetcat);
        $outputNetcat = explode(':', $outputNetcat[0]);

        exec('whereis nmap', $outputNmap, $returnNmap);
        $outputNmap = explode(':', $outputNmap[0]);
        $netcatOrNmap = !empty($outputNetcat[1]) || !empty($outputNmap[1]);

        $pdoPgsql = @extension_loaded('pdo_pgsql');
        $pgsql = @extension_loaded('pgsql');
        $mbstring = @extension_loaded('mbstring');
        $fileinfo = @extension_loaded('fileinfo');
        $gd = @extension_loaded('gd');
        $imagick = @extension_loaded('imagick');
        $imap = @extension_loaded('imap');
        $xsl = @extension_loaded('xsl');
        $gettext = @extension_loaded('gettext');
        $xmlrpc = @extension_loaded('xmlrpc');
        $curl = @extension_loaded('curl');
        $zip = @extension_loaded('zip');

        $writable = is_writable('.') && is_readable('.');

        $displayErrors = (ini_get('display_errors') == '1');
        $errorReporting = CoreController::getErrorReportingFromPhpIni();
        $errorReporting = !in_array(8, $errorReporting);

        $prerequisites = [
            'phpVersion'        => $phpVersion,
            'phpVersionValid'   => $phpVersionValid,
            'unoconv'           => $unoconv,
            'netcatOrNmap'      => $netcatOrNmap,
            'pdoPgsql'          => $pdoPgsql,
            'pgsql'             => $pgsql,
            'mbstring'          => $mbstring,
            'fileinfo'          => $fileinfo,
            'gd'                => $gd,
            'imagick'           => $imagick,
            'imap'              => $imap,
            'xsl'               => $xsl,
            'gettext'           => $gettext,
            'xmlrpc'            => $xmlrpc,
            'curl'              => $curl,
            'zip'               => $zip,
            'writable'          => $writable,
            'displayErrors'     => $displayErrors,
            'errorReporting'    => $errorReporting
        ];

        return $response->withJson(['prerequisites' => $prerequisites]);
    }

    public function checkDatabaseConnection(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['server'])) {
            return $response->withStatus(400)->withJson(['errors' => 'QueryParams server is empty or not a string']);
        } elseif (!Validator::intVal()->notEmpty()->validate($queryParams['port'])) {
            return $response->withStatus(400)->withJson(['errors' => 'QueryParams port is empty or not an integer']);
        } elseif (!Validator::stringType()->notEmpty()->validate($queryParams['user'])) {
            return $response->withStatus(400)->withJson(['errors' => 'QueryParams user is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($queryParams['password'])) {
            return $response->withStatus(400)->withJson(['errors' => 'QueryParams password is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($queryParams['name'])) {
            return $response->withStatus(400)->withJson(['errors' => 'QueryParams name is empty or not a string']);
        }

        $options = [
            \PDO::ATTR_ERRMODE      => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_CASE         => \PDO::CASE_NATURAL
        ];

        $firstTry = true;
        $dsn = "pgsql:host={$queryParams['server']};port={$queryParams['port']};dbname={$queryParams['name']}";
        try {
            $db = new \PDO($dsn, $queryParams['user'], $queryParams['password'], $options);
        } catch (\PDOException $PDOException) {
            $firstTry = false;
            $dsn = "pgsql:host={$queryParams['server']};port={$queryParams['port']};dbname=postgres";
            try {
                $db = new \PDO($dsn, $queryParams['user'], $queryParams['password'], $options);
            } catch (\PDOException $PDOException) {
                return $response->withStatus(400)->withJson(['errors' => 'Database connection failed']);
            }
        }

        if ($firstTry) {
            $query = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema not in ('pg_catalog', 'information_schema')");
            $row = $query->fetch(\PDO::FETCH_ASSOC);
            if (!empty($row)) {
                return $response->withStatus(400)->withJson(['errors' => 'Given database has tables']);
            }
            return $response->withJson(['warning' => 'Database exists']);
        }

        return $response->withStatus(204);
    }

    public function getSQLDataFiles(Request $request, Response $response)
    {
        $dataFiles = [];

        $sqlFiles =  scandir('sql');
        foreach ($sqlFiles as $sqlFile) {
            if ($sqlFile == '.' || $sqlFile == '..') {
                continue;
            }
            if (strpos($sqlFile, 'data_') === 0) {
                $dataFiles[] = str_replace('.sql', '', $sqlFile);
            }
        }

        return $response->withJson(['dataFiles' => $dataFiles]);
    }

    public function checkDocservers(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['path'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Queryparams path is empty or not a string']);
        }

        $path = urldecode($queryParams['path']);
        $path = rtrim($path, '/');

        $multiplesPaths = explode('/', $path);

        while (!empty($multiplesPaths)) {
            $pathToTest = implode('/', $multiplesPaths);
            if (empty($pathToTest)) {
                $pathToTest = '/';
            }
            if (is_dir($pathToTest)) {
                if (!is_readable($pathToTest) || !is_writable($pathToTest)) {
                    return $response->withStatus(400)->withJson(['errors' => "Queryparams path is not readable or writable"]);
                }
                break;
            }
            unset($multiplesPaths[count($multiplesPaths) - 1]);
        }

        return $response->withStatus(204);
    }

    public function checkCustomName(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!Validator::stringType()->notEmpty()->validate($queryParams['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Queryparams customId is empty or not a string']);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]*$/', $queryParams['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Queryparams customId has unauthorized characters']);
        }

        if (is_dir("custom/{$queryParams['customId']}")) {
            return $response->withStatus(400)->withJson(['errors' => "Custom already exists"]);
        }

        return $response->withStatus(204);
    }

    public function createCustom(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId is empty or not a string']);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]*$/', $body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId has unauthorized characters']);
        }

        if (is_dir("custom/{$body['customId']}")) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom with this name already exists']);
        } elseif (!@mkdir("custom/{$body['customId']}/apps/maarch_entreprise/xml", 0755, true)) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom folder creation failed']);
        }

        if (!is_file("custom/custom.json")) {
            $fp = fopen('custom/custom.json', 'w');
            fwrite($fp, json_encode([], JSON_PRETTY_PRINT));
            fclose($fp);
        }

        $customFile = CoreConfigModel::getJsonLoaded(['path' => 'custom/custom.json']);
        $customFile[] = [
            'id'    => $body['customId'],
            'uri'   => null,
            'path'  => $body['customId']
        ];
        $fp = fopen('custom/custom.json', 'w');
        fwrite($fp, json_encode($customFile, JSON_PRETTY_PRINT));
        fclose($fp);

        $jsonFile = [
            'config'    => [
                'lang'              => $body['lang'] ?? 'fr',
                'applicationName'   => $body['applicationName'] ?? $body['customId'],
                'cookieTime'        => 10080,
                'timezone'          => 'Europe/Paris'
            ],
            'database'  => []
        ];
        $fp = fopen("custom/{$body['customId']}/apps/maarch_entreprise/xml/config.json", 'w');
        fwrite($fp, json_encode($jsonFile, JSON_PRETTY_PRINT));
        fclose($fp);

        $cmd = 'ln -s ' . realpath('.') . "/ {$body['customId']}";
        exec($cmd);

        file_put_contents("custom/{$body['customId']}/initializing.lck", 1);

        return $response->withStatus(204);
    }

    public function createDatabase(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['server'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body server is empty or not a string']);
        } elseif (!Validator::intVal()->notEmpty()->validate($body['port'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body port is empty or not an integer']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['user'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body user is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['password'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body password is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['name'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body name is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId is empty or not a string']);
        } elseif (!is_file("custom/{$body['customId']}/initializing.lck")) {
            return $response->withStatus(403)->withJson(['errors' => 'Custom is already installed']);
        } elseif (!is_file("custom/{$body['customId']}/apps/maarch_entreprise/xml/config.json")) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom does not exist']);
        }

        $options = [
            \PDO::ATTR_ERRMODE      => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_CASE         => \PDO::CASE_NATURAL
        ];

        $connection = "host={$body['server']} port={$body['port']} user={$body['user']} password={$body['password']} dbname={$body['name']}";
        $connected = @pg_connect($connection);
        if ($connected) {
            $result = @pg_query("SELECT table_name FROM information_schema.tables WHERE table_schema not in ('pg_catalog', 'information_schema')");
            $row = pg_fetch_row($result);
            if (!empty($row)) {
                return $response->withStatus(400)->withJson(['errors' => 'Given database has tables']);
            }
            pg_close();
        } else {
            $connection = "host={$body['server']} port={$body['port']} user={$body['user']} password={$body['password']} dbname=postgres";
            if (!@pg_connect($connection)) {
                return $response->withStatus(400)->withJson(['errors' => 'Database connection failed']);
            }

            $result = @pg_query("CREATE DATABASE \"{$body['name']}\" WITH TEMPLATE template0 ENCODING = 'UTF8'");
            if (!$result) {
                return $response->withStatus(400)->withJson(['errors' => 'Database creation failed']);
            }

            @pg_query("ALTER DATABASE '{$body['name']}' SET DateStyle =iso, dmy");
            pg_close();
        }

        $options = [
            \PDO::ATTR_PERSISTENT   => true,
            \PDO::ATTR_ERRMODE      => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_CASE         => \PDO::CASE_NATURAL
        ];

        $dsn = "pgsql:host={$body['server']};port={$body['port']};dbname={$body['name']}";
        $db = new \PDO($dsn, $body['user'], $body['password'], $options);

        if (!is_file('sql/structure.sql')) {
            return $response->withStatus(400)->withJson(['errors' => 'File sql/structure.sql does not exist']);
        }
        $fileContent = file_get_contents('sql/structure.sql');
        $result = $db->exec($fileContent);
        if ($result === false) {
            return $response->withStatus(400)->withJson(['errors' => 'Request failed : run structure.sql']);
        }

        if (!empty($body['data'])) {
            if (!is_file("sql/{$body['data']}.sql")) {
                return $response->withStatus(400)->withJson(['errors' => "File sql/{$body['data']}.sql does not exist"]);
            }
            $fileContent = file_get_contents("sql/{$body['data']}.sql");
            $result = $db->exec($fileContent);
            if ($result ===  false) {
                return $response->withStatus(400)->withJson(['errors' => "Request failed : run {$body['data']}.sql"]);
            }
        }

        $configFile = CoreConfigModel::getJsonLoaded(['path' => "custom/{$body['customId']}/apps/maarch_entreprise/xml/config.json"]);
        $configFile['database'] = [
            [
                "server"    => $body['server'],
                "port"      => $body['port'],
                "type"      => 'POSTGRESQL',
                "name"      => $body['name'],
                "user"      => $body['user'],
                "password"  => $body['password']
            ]
        ];

        $fp = fopen("custom/{$body['customId']}/apps/maarch_entreprise/xml/config.json", 'w');
        fwrite($fp, json_encode($configFile, JSON_PRETTY_PRINT));
        fclose($fp);

        return $response->withStatus(204);
    }

    public function createDocservers(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['path'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body path is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId is empty or not a string']);
        } elseif (!is_file("custom/{$body['customId']}/initializing.lck")) {
            return $response->withStatus(403)->withJson(['errors' => 'Custom is already installed']);
        } elseif (!is_file("custom/{$body['customId']}/apps/maarch_entreprise/xml/config.json")) {
            return $response->withStatus(400)->withJson(['errors' => 'Custom does not exist']);
        }

        $body['path'] = rtrim($body['path'], '/');

        if (!is_dir($body['path'])) {
            if (!@mkdir($body['path'], 0755, true)) {
                return $response->withStatus(400)->withJson(['errors' => "Folder creation failed for path : {$body['path']}"]);
            }
        } elseif (!is_readable($body['path']) || !is_writable($body['path'])) {
            return $response->withStatus(400)->withJson(['errors' => "Body path is not readable or writable"]);
        }

        $docservers = [
            'AI'                        => 'ai',
            'RESOURCES'                 => 'resources',
            'ATTACHMENTS'               => 'attachments',
            'CONVERT_RESOURCES'         => 'convert_resources',
            'CONVERT_ATTACH'            => 'convert_attachments',
            'TNL_RESOURCES'             => 'thumbnails_resources',
            'TNL_ATTACHMENTS'           => 'thumbnails_attachments',
            'FULLTEXT_RESOURCES'        => 'fulltext_resources',
            'FULLTEXT_ATTACHMENTS'      => 'fulltext_attachments',
            'TEMPLATES'                 => 'templates',
            'ARCHIVETRANSFER'           => 'archive_transfer',
            'ACKNOWLEDGEMENT_RECEIPTS'  => 'acknowledgement_receipts'
        ];

        foreach ($docservers as $docserver) {
            if (!@mkdir("{$body['path']}/{$body['customId']}/{$docserver}", 0755, true)) {
                return $response->withStatus(400)->withJson(['errors' => "Docserver folder creation failed for path : {$body['path']}/{$body['customId']}/{$docserver}"]);
            }
        }

        $templatesPath = "{$body['path']}/{$body['customId']}/templates/0000";
        if (!@mkdir($templatesPath, 0755, true)) {
            return $response->withJson(['success' => "Docservers created but templates folder creation failed"]);
        }

        $templatesToCopy =  scandir('install/templates/0000');
        foreach ($templatesToCopy as $templateToCopy) {
            if ($templateToCopy == '.' || $templateToCopy == '..') {
                continue;
            }

            copy("install/templates/0000/{$templateToCopy}", "{$templatesPath}/{$templateToCopy}");
        }

        DatabasePDO::reset();
        new DatabasePDO(['customId' => $body['customId']]);
        DatabaseModel::update([
            'table'     => 'docservers',
            'postSet'   => ['path_template' => "replace(path_template, '/opt/maarch/docservers', '{$body['path']}/{$body['customId']}')"],
            'where'     => ['1 = 1']
        ]);

        return $response->withStatus(204);
    }

    public function createCustomization(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['bodyLoginBackground'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body bodyLoginBackground is empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['logo'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body logo is empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['loginMessage'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body loginMessage is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['homeMessage'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body homeMessage is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId is empty or not a string']);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]*$/', $body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId has unauthorized characters']);
        } elseif (!is_file("custom/{$body['customId']}/initializing.lck")) {
            return $response->withStatus(403)->withJson(['errors' => 'Custom is already installed']);
        }

        if (strpos($body['bodyLoginBackground'], 'data:image/jpeg;base64,') === false) {
            if (!is_file("dist/assets/{$body['bodyLoginBackground']}")) {
                return $response->withStatus(400)->withJson(['errors' => 'Body bodyLogin does not exist']);
            }
            if ($body['bodyLoginBackground'] != 'bodylogin.jpg') {
                copy("dist/assets/{$body['bodyLoginBackground']}", "custom/{$body['customId']}/img/bodylogin.jpg");
            }
        } else {
            $tmpPath = CoreConfigModel::getTmpPath();
            $tmpFileName = $tmpPath . 'installer_body_' . rand() . '_file.jpg';
            $file = base64_decode($body['bodyLoginBackground']);
            file_put_contents($tmpFileName, $file);

            $size = strlen($file);
            $imageSizes = getimagesize($tmpFileName);
            if ($imageSizes[0] < 1920 || $imageSizes[1] < 1080) {
                return $response->withStatus(400)->withJson(['errors' => 'BodyLogin image is not wide enough']);
            } elseif ($size > 10000000) {
                return $response->withStatus(400)->withJson(['errors' => 'BodyLogin size is not allowed']);
            }
            copy($tmpFileName, "custom/{$body['customId']}/img/bodylogin.jpg");
        }

        if (strpos($body['bodyLoginBackground'], 'data:image/svg+xml;base64,') !== false) {
            $tmpPath = CoreConfigModel::getTmpPath();
            $tmpFileName = $tmpPath . 'installer_logo_' . rand() . '_file.svg';
            $file = base64_decode($body['logo']);
            file_put_contents($tmpFileName, $file);

            $size = strlen($file);
            if ($size > 5000000) {
                return $response->withStatus(400)->withJson(['errors' => 'Logo size is not allowed']);
            }
            copy($tmpFileName, "custom/{$body['customId']}/img/logo.svg");
        }

        DatabasePDO::reset();
        new DatabasePDO(['customId' => $body['customId']]);
        DatabaseModel::update([
            'table'     => 'parameters',
            'set'       => ['param_value_string' => $body['loginMessage']],
            'where'     => ['id = ?'],
            'data'      => ['loginpage_message']
        ]);
        DatabaseModel::update([
            'table'     => 'parameters',
            'set'       => ['param_value_string' => $body['homeMessage']],
            'where'     => ['id = ?'],
            'data'      => ['homepage_message']
        ]);

        return $response->withStatus(204);
    }

    public function updateAdministrator(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId is empty or not a string']);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]*$/', $body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId has unauthorized characters']);
        } elseif (!is_file("custom/{$body['customId']}/initializing.lck")) {
            return $response->withStatus(403)->withJson(['errors' => 'Custom is already installed']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['password'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Body password is empty or not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['email']) || !filter_var($body['mail'], FILTER_VALIDATE_EMAIL)) {
            return $response->withStatus(403)->withJson(['errors' => 'Body password is empty, not a string or not a valid email']);
        }

        DatabasePDO::reset();
        new DatabasePDO(['customId' => $body['customId']]);
        DatabaseModel::update([
            'table'     => 'users',
            'set'       => [
                'password'  => AuthenticationModel::getPasswordHash($body['password']),
                'mail'      => $body['email']
            ],
            'where'     => ['user_id = ?'],
            'data'      => ['superadmin']
        ]);

        return $response->withStatus(204);
    }

    public function terminateInstaller(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!Validator::stringType()->notEmpty()->validate($body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId is empty or not a string']);
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]*$/', $body['customId'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Body customId has unauthorized characters']);
        } elseif (!is_file("custom/{$body['customId']}/initializing.lck")) {
            return $response->withStatus(403)->withJson(['errors' => 'Custom is already installed']);
        }

        unlink("custom/{$body['customId']}/initializing.lck");

        return $response->withStatus(204);
    }
}
