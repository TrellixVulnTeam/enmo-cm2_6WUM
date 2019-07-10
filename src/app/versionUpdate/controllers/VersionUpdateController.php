<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Version Update Controller
 * @author dev@maarch.org
 */

namespace VersionUpdate\controllers;

use Gitlab\Client;
use Group\models\ServiceModel;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\CoreConfigModel;

class VersionUpdateController
{
    public function get(Request $request, Response $response)
    {
        if (!ServiceModel::hasService(['id' => 'admin_update_control', 'userId' => $GLOBALS['userId'], 'location' => 'apps', 'type' => 'admin'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $client = Client::create('https://labs.maarch.org/api/v4/');
        try {
            $tags = $client->api('tags')->all('12');
        } catch (\Exception $e) {
            return $response->withJson(['errors' => $e->getMessage()]);
        }

        $applicationVersion = CoreConfigModel::getApplicationVersion();

        if (empty($applicationVersion)) {
            return $response->withStatus(400)->withJson(['errors' => "Can't load xml applicationVersion"]);
        } else {
            $currentVersion = $applicationVersion;
        }

        $versions = explode('.', $currentVersion);
        $currentVersionBranch = "{$versions[0]}.{$versions[1]}";
        $currentVersionTag = $versions[2];
        $currentVersionBranchYear = $versions[0];
        $currentVersionBranchMonth = $versions[1];

        $availableMinorVersions = [];
        $availableMajorVersions = [];

        foreach ($tags as $value) {
            if (!preg_match("/^\d{2}\.\d{2}\.\d+$/", $value['name'])) {
                continue;
            }
            $explodedValue = explode('.', $value['name']);
            $tag = $explodedValue[2];

            $pos = strpos($value['name'], $currentVersionBranch);
            if ($pos === false) {
                $year = substr($value['name'], 0, 2);
                $month = substr($value['name'], 3, 2);
                if (($year == $currentVersionBranchYear && $month > $currentVersionBranchMonth) || $year > $currentVersionBranchYear) {
                    $availableMajorVersions[] = $value['name'];
                }
            } else {
                if ($tag > $currentVersionTag) {
                    $availableMinorVersions[] = $value['name'];
                }
            }
        }

        natcasesort($availableMinorVersions);
        natcasesort($availableMajorVersions);

        if (empty($availableMinorVersions)) {
            $lastAvailableMinorVersion = null;
        } else {
            $lastAvailableMinorVersion = $availableMinorVersions[0];
        }

        if (empty($availableMajorVersions)) {
            $lastAvailableMajorVersion = null;
        } else {
            $lastAvailableMajorVersion = $availableMajorVersions[0];
        }

        $output = [];
        $diff = exec('git diff', $output);
        $stagedDiff = exec('git diff --staged', $output);

        return $response->withJson([
            'lastAvailableMinorVersion' => $lastAvailableMinorVersion,
            'lastAvailableMajorVersion' => $lastAvailableMajorVersion,
            'currentVersion'            => $currentVersion,
            'canUpdate'                 => empty($output) && empty($diff) && empty($stagedDiff)
        ]);
    }

    public function update(Request $request, Response $response)
    {
        if (!ServiceModel::hasService(['id' => 'admin_update_control', 'userId' => $GLOBALS['userId'], 'location' => 'apps', 'type' => 'admin'])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $client = Client::create('https://labs.maarch.org/api/v4/');
        try {
            $tags = $client->api('tags')->all('12');
        } catch (\Exception $e) {
            return $response->withJson(['errors' => $e->getMessage()]);
        }

        $applicationVersion = CoreConfigModel::getApplicationVersion();

        if (empty($applicationVersion)) {
            return $response->withStatus(400)->withJson(['errors' => "Can't load xml applicationVersion"]);
        } else {
            $currentVersion = $applicationVersion;
        }

        $versions = explode('.', $currentVersion);
        $currentVersionBranch = "{$versions[0]}.{$versions[1]}";
        $currentVersionTag = $versions[2];

        $availableMinorVersions = [];

        foreach ($tags as $value) {
            if (strpos($value['name'], $currentVersionBranch) === false) {
                continue;
            }
            $explodedValue = explode('.', $value['name']);
            $tag = $explodedValue[2];

            if ($tag > $currentVersionTag) {
                $availableMinorVersions[] = $value['name'];
            }
        }

        if (empty($availableMinorVersions)) {
            return $response->withStatus(400)->withJson(['errors' => 'No minor versions available']);
        }

        natcasesort($availableMinorVersions);

        $minorVersion = $availableMinorVersions[0];

        $output = [];
        $diff = exec('git diff', $output);
        $stagedDiff = exec('git diff --staged', $output);

        if (!empty($output) || !empty($diff) || !empty($stagedDiff)) {
            return $response->withStatus(400)->withJson(['errors' => 'Some files are modified. Can not update application']);
        }

        $output = [];
        exec('git fetch');
        exec("git checkout {$minorVersion}", $output);

        $log = "Application updated from {$currentVersion} to {$minorVersion}\nCheckout response => " . implode(' ', $output) . "\n";
        file_put_contents('updateVersion.log', $log, FILE_APPEND);

        return $response->withStatus(204);
    }
}
