<?php

namespace CatLab\Assets\Laravel\Controllers;

use CatLab\Assets\Laravel\Helpers\AssetFactory;
use CatLab\Assets\Laravel\Models\Asset;
use Illuminate\Support\Str;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Request;
use Response;

/**
 * Class AssetController
 * @package CatLab\Assets\Controllers
 */
class AssetController
{
    const QUERY_PARAM_SHAPE = 'shape';
    const QUERY_PARAM_SQUARE = 'square';
    const QUERY_PARAM_SIZE = 'size';
    const QUERY_PARAM_WIDTH = 'width';
    const QUERY_PARAM_HEIGHT = 'height';
    const QUERY_PARAM_BORDER_WIDTH = 'borderWidth';
    const QUERY_PARAM_BORDER_COLOR = 'borderColor';
    const QUERY_PARAM_CACHE = 'cache';

    const QUERY_SHAPE_CIRCLE = 'circle';

    /**
     * @param $assetId
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function view($assetId)
    {
        /** @var Asset $asset */
        $asset = AssetFactory::find($assetId);
        if (!$asset) {
            abort(404, 'Asset ' . $assetId . ' not found.');
        }

        return $this->viewAsset($asset);
    }

    /**
     * @param Asset $asset
     * @return array
     */
    protected function getImageSize(Asset $asset)
    {
        if (Request::input(self::QUERY_PARAM_SHAPE)) {

            $shape = Request::input(self::QUERY_PARAM_SHAPE);
            switch ($shape) {
                case self::QUERY_PARAM_SQUARE:
                case self::QUERY_SHAPE_CIRCLE:

                    if (Request::input(self::QUERY_PARAM_SIZE)) {
                        return [
                            Request::input(self::QUERY_PARAM_SIZE),
                            Request::input(self::QUERY_PARAM_SIZE)
                        ];
                    }
            }

        }

        if (Request::input(self::QUERY_PARAM_SQUARE)) {
            return [
                Request::input(self::QUERY_PARAM_SQUARE),
                Request::input(self::QUERY_PARAM_SQUARE)
            ];
        }

        if (Request::input(self::QUERY_SHAPE_CIRCLE)) {
            return [
                Request::input(self::QUERY_SHAPE_CIRCLE),
                Request::input(self::QUERY_SHAPE_CIRCLE)
            ];
        }

        elseif (
            Request::input(self::QUERY_PARAM_WIDTH) &&
            Request::input(self::QUERY_PARAM_HEIGHT)
        ) {
            return [
                Request::input(self::QUERY_PARAM_WIDTH),
                Request::input(self::QUERY_PARAM_HEIGHT)
            ];
        }

        // allow only providing one dimension
        elseif (
            Request::input(self::QUERY_PARAM_WIDTH) ||
            Request::input(self::QUERY_PARAM_HEIGHT)
        ) {
            // If asset has no width or height, we can't do these calculations.
            if (
                !$asset->width ||
                !$asset->height
            ) {
                return [ null, null ];
            }

            if (Request::input(self::QUERY_PARAM_WIDTH)) {
                $targetWidth = intval(Request::input(self::QUERY_PARAM_WIDTH));
                $factor = $targetWidth / $asset->width;
            } else {
                $targetHeight = intval(Request::input(self::QUERY_PARAM_HEIGHT));
                $factor = $targetHeight / $asset->height;
            }

            $targetWidth = ceil($factor * $asset->width);
            $targetHeight = ceil($factor * $asset->height);

            return [ $targetWidth, $targetHeight ];
        }

        return [ null, null ];
    }

    /**
     * @param Asset $asset
     * @return array
     */
    protected function getCacheHeaders(Asset $asset)
    {
        $expireInterval = new DateInterval('P1Y');
        $expireDate = (new DateTime())->add($expireInterval);

        return [
            'Expires' => $expireDate->format('r'),
            'Last-Modified' => $asset->created_at ? $asset->created_at->format('r') : null,
            'Cache-Control' => 'max-age=' . $this->dateIntervalToSeconds($expireInterval) . ', public'
        ];
    }

    /**
     * @param DateInterval $dateInterval
     * @return int seconds
     */
    protected function dateIntervalToSeconds($dateInterval)
    {
        $reference = new DateTimeImmutable;
        $endTime = $reference->add($dateInterval);

        return $endTime->getTimestamp() - $reference->getTimestamp();
    }

    /**
     * @param Asset $asset
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function viewAsset(Asset $asset)
    {
        $asset->updateLastUsed();

        if ($asset->isImage() && !$asset->isSvg()) {
            return $this->getImageResponse($asset);
        }

        // Do some magic here.
        return $this->getAssetResponse($asset, []);
    }

    /**
     * @param Asset $asset
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getImageResponse(Asset $asset)
    {
        $shape = Request::input(self::QUERY_PARAM_SHAPE);
        $refresh = Request::input(self::QUERY_PARAM_CACHE) == 0;

        $borderWidth = Request::input(self::QUERY_PARAM_BORDER_WIDTH);
        $borderColor = Request::input(self::QUERY_PARAM_BORDER_COLOR);
        if ($borderColor && !Str::startsWith($borderColor, '#')) {
            $borderColor = '#' . $borderColor;
        }

        $targetSize = $this->getImageSize($asset);
        $variation = $asset->getResizedImage(
            $targetSize[0],
            $targetSize[1],
            $shape,
            $borderWidth,
            $borderColor,
            $refresh
        );

        // Set last used for variation as well, as we will use this to clear 'cached' resized images.
        $variation->updateLastUsed();

        //return $this->getAssetResponse($variation);
        $response = \Response::make(
            $variation->getData(),
            200,
            array_merge(
                [
                    'Content-type' => $asset->mimetype
                ],
                $this->getCacheHeaders($asset)
            )
        );

        return $response;
    }

    /**
     * @param Asset $asset
     * @param string[] $forceHeaders
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getAssetResponse(Asset $asset, $forceHeaders = [])
    {
        $headers = array_merge([
            'Content-type' => $asset->mimetype
        ], $forceHeaders);

        $disk = $asset->getDisk();

        $stream = $disk->readStream($asset->path);

        $size = $disk->size($asset->path);

        // Tell the browser that we accept ranges
        $headers['Accept-Ranges'] = '0-' . $size;

        $start = 0;
        $end = $size - 1;

        $httpStatus = 200;

        // Accept ranges
        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end = $end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

            if (strpos($range, ',') !== false) {
                return Response::make('Requested Range Not Satisfiable', 416, $headers);
            }

            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
            }

            $c_end = ($c_end > $end) ? $end : $c_end;

            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                return Response::make('Requested Range Not Satisfiable', 416, $headers);
            }

            $start = $c_start;
            $end = $c_end;

            // Rewrite the content range header
            $headers['Content-Range'] = "Bytes {$start}-{$end}/{$size}";

            $httpStatus = 206;
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = $length;

        // @todo add more security here.
        $headers['Access-Control-Allow-Origin'] = '*';
        //$headers['Access-Control-Request-Headers'] = \Config::get('cors.allowedHeaders');
        //$headers['Access-Control-Request-Method'] = \Config::get('cors.allowedMethods');
        //$headers['Access-Control-Expose-Headers'] = \Config::get('cors.exposedHeaders');

        return Response::stream(
            function() use ($stream, $start, $end) {
                $buffer = 102400;

                if ($start > 0) {
                    fseek($stream, $start);
                }

                $i = $start;

                while(!feof($stream) && $i <= $end) {
                    $bytesToRead = $buffer;

                    if(($i+$bytesToRead) > $end) {
                        $bytesToRead = $end - $i + 1;
                    }

                    echo fread($stream, $bytesToRead);

                    $i += $bytesToRead;
                }

                fclose($stream);
            },
            $httpStatus,
            array_merge($headers, $this->getCacheHeaders($asset))
        );
    }
}
