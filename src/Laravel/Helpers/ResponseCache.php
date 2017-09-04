<?php

namespace CatLab\Assets\Laravel\Helpers;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class RequestCache
 * @package CatLab\Assets\Laravel\Helpers
 */
class ResponseCache
{
    private $path;

    /**
     * Cache constructor.
     * @param $path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Output content if a cached request is found.
     */
    public function outputIfExists()
    {
        if (isset($_GET['nocache'])) {
            return;
        }

        // Look for cached file
        if ($this->has()) {

            $headers = true;
            $handle = fopen($this->getPath(), 'r');

            $resHeaders = [
                'X-Image-From-Cache' => 'true',
                'X-Response-From-Cache' => 'true'
            ];

            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    if ($headers) {
                        if ($line === "\n") {
                            $headers = false;
                            foreach ($resHeaders as $k => $v) {
                                header($k . ': ' . $v);
                            }
                            continue;
                        } else {
                            $parts = explode('=', $line);
                            $headerName = array_shift($parts);
                            $headerValue = implode('=', $parts);
                            $resHeaders[$headerName] = $headerValue;

                            // Check if this is the expire header
                            if (strtolower($headerName) === 'expires') {
                                $expireDate = new DateTime($headerValue);
                                if ((new DateTime()) > $expireDate) {
                                    $this->remove();
                                }
                            }
                        }
                    } else {
                        echo $line;
                    }
                }
                fclose($handle);
                exit;
            } else {
                // error opening the file.
                return;
            }
        }
    }

    /**
     * @return bool
     */
    public function has()
    {
        return file_exists($this->getPath());
    }

    /**
     * @return bool
     */
    public function remove()
    {
        return unlink($this->getPath());
    }

    /**
     * @return bool|string
     */
    public function get()
    {
        return file_get_contents($this->getPath());
    }

    /**
     * @param Response $response
     */
    public function cache(Response $response)
    {
        $content = '';
        foreach ($response->headers->all() as $k => $v) {
            switch ($k) {
                case 'content-type':
                case 'expires':
                case 'cache-control':
                case 'last-modified':
                case 'access-control-allow-origin':

                    $content .= $k . '=' . $v[0] . "\n";
                    break;
            }
        }

        $content .= "\n";
        $content .= $response->getContent();

        file_put_contents($this->getPath(), $content);
    }

    /**
     *
     */
    protected function getPath()
    {
        return $this->path . '/response_cache/response:' . md5($_SERVER['REQUEST_URI']);
    }
}