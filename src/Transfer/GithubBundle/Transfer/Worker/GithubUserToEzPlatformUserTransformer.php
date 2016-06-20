<?php

namespace Transfer\GithubBundle\Transfer\Worker;

use SplFileInfo;
use Transfer\EzPlatform\Repository\Values\UserGroupObject;
use Transfer\EzPlatform\Repository\Values\UserObject;
use Transfer\Worker\WorkerInterface;

/**
 * Class GithubUserToEzPlatformUserTransformer.
 */
class GithubUserToEzPlatformUserTransformer implements WorkerInterface
{
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
            $data['type'] !== 'User'
        ) {
            return $data;
        }

        $user = new UserObject(
            array(
                'username' => $data['login'],
                'email' => $data['email'] ?: 'placeholder_'.$data['login'].'@example.com',
                'password' => $this->getRandomPassword(),
                'main_language_code' => 'eng-GB',
                'content_type' => 'github_user',
                'fields' => array(
                    'login' => $data['login'],
                    'name' => $data['name'],
                    'image' => $this->getUrlToLocalImage($data['avatar_url']),
                    'company' => $data['company'],
                    'url' => $data['html_url'],
                    'github_id' => $data['id'],
                    'location' => $data['location'],
                    'bio' => $data['bio'],
                ),
                'parents' => array(
                    new UserGroupObject(array(
                        'parent_id' => 4,
                        'content_type_identifier' => 'user_group',
                        'main_language_code' => 'eng-GB',
                        'fields' => array(
                            'name' => 'Github Users',
                        ),
                        'remote_id' => 'usergroup_githubusers',
                    )),
                ),
            )
        );

        return $user;
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

    private function getRandomPassword()
    {
        return sha1(bin2hex(openssl_random_pseudo_bytes(32)));
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
