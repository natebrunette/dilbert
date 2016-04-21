<?php
/**
 * File TwitterUploadClient.php
 */

namespace Tebru\Dilbot\Client;

use Tebru\Retrofit\Annotation\FormUrlEncoded;
use Tebru\Retrofit\Annotation\Part;
use Tebru\Retrofit\Annotation\POST;
use Tebru\Retrofit\Annotation\Returns;


/**
 * Interface TwitterUploadClient
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * @FormUrlEncoded()
 */
interface TwitterUploadClient
{
    /**
     * Upload an image to twitter
     *
     * @param string $image
     * @return string
     *
     * @POST("/media/upload.json")
     * @Part("media", var="image")
     * @Returns("array")
     */
    public function uploadImage(string $image);

}
