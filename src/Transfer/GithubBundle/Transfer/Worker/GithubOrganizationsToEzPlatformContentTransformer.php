<?php

namespace Transfer\GithubBundle\Transfer\Worker;

use SplFileInfo;
use Transfer\Data\TreeObject;
use Transfer\EzPlatform\Repository\Values\ContentObject;
use Transfer\EzPlatform\Repository\Values\LocationObject;
use Transfer\Worker\WorkerInterface;

/**
 * Class GithubOrganizationsToEzPlatformContentTransformer.
 */
class GithubOrganizationsToEzPlatformContentTransformer implements WorkerInterface
{
    /**
     * @var string[]
     */
    private $importedImages = [];

    /**
     * {@inheritdoc}
     */
    public function handle($data)
    {
        // Skip objects which are not user-related
        if (
            !is_array($data) ||
            !isset($data['type']) ||
            $data['type'] !== 'Organization'
        ) {
            return $data;
        }

        $organization = new ContentObject(
            array(
                'login'         => $data['login'],
                'title'         => $data['name'],
                'github_id'     => $data['id'],
                //'image'         => $this->getUrlToLocalImage($data['avatar_url']),
                'url'           => $data['html_url'],
                'description'   => $data['description'],
                'blog'          => $data['blog'],
            ),
            array(
                'remote_id' => 'organization_'.$data['id'],
                'content_type_identifier' => 'organization',
                'main_language_code' => 'eng-GB',
                'parent_locations' => array(
                    new LocationObject(array(
                        'parent_location_id' => 57,
                        'remote_id' => 'location_organization_'.$data['id'],
                    )),
                ),
            )
        );

        $tree = new TreeObject($organization);
        $tree->setProperty('parent_location_id', 57);

        if (
            isset($data['repositories']) &&
            is_array($data['repositories']) &&
            count($data['repositories']) > 0
        ) {
            foreach ($data['repositories'] as $repository) {
                $repo = new ContentObject(array(
                    'title'         => $repository['name'],
                    'full_name'     => $repository['full_name'],
                    'github_id'     => $repository['id'],
                    'description'   => $repository['description'],
                    'url'           => $repository['html_url'],
                    'git_url'       => $repository['git_url'],
                    'homepage'      => $repository['homepage'],
                    'language'      => $repository['language'],
                    'default_branch'=> $repository['default_branch'],
                ), array(
                    'remote_id' => 'repository_'.$repository['id'],
                    'content_type_identifier' => 'repository',
                    'main_language_code' => 'eng-GB',
                ));
                echo $repository['name'] . PHP_EOL;
                $tree->addNode($repo);
            }
        }

        return $tree;
    }

    private function getUrlToLocalImage($avatarUrl)
    {
        $localName = tempnam(sys_get_temp_dir(), 'temp');
        copy($avatarUrl, $localName);
        $this->importedImages[] = $localName;

        $info = new SplFileInfo($localName);
        $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);

        if (!$extension) {
            $extension = 'png';
            imagepng(imagecreatefromstring(file_get_contents($localName)), $localName.'.'.$extension);
            $localName .= '.'.$extension;
            $this->importedImages[] = $localName;
        }

        return array(
            'inputUri' => $localName,
            'fileSize' => filesize($localName),
            'fileName' => basename($localName),
        );
    }

    public function __destruct()
    {
        if (count($this->importedImages) > 0) {
            foreach ($this->importedImages as $image) {
                if (file_exists($image)) {
                    unlink($image);
                }
            }
        }
    }
}
