<?php
/**
 * File TwitterUploadClient.php
 */

namespace Tebru\DilbertPics\Client;

use Tebru\Retrofit\Annotation as Rest;

/**
 * Interface TwitterUploadClient
 *
 * @author Nate Brunette <n@tebru.net>
 */
interface TwitterUploadClient
{
    /**
     * @Rest\POST("/media/upload.json")
     * @Rest\Part("media", var="image")
     * @Rest\Returns("array")
     */
    public function uploadImage($image);

}
