<?php
namespace Cs\Router\Services;

use Psr\Http\Message\ResponseInterface as Response;
use Cs\Router\Util\Assert;
use Cs\Router\Traits\HttpStatus;

Class ResponseHandler extends Assert {
    use HttpStatus;
    /**
     * setResponse
     *
     * @param  mixed $response
     * @param  mixed $result
     * @param  mixed $type
     *
     * @return void
     */
    public function setResponse(
        Response $response, $result, $type
    ) {
        $this->body = $response;
        $code = $this->getStatusCode($result);
        switch(strtolower($type)) {
            case 'json':
                return $this->getJsonResponse($result, $code);
            case 'raw':
                return $this->getRawResponse($result, $code);
            case 'download':
                return $this->getDownloadResponse($result, $code);
            default:
                return $this->getRawResponse($result, $code);
        }
    }

    /**
     * jsonResponse
     *
     * @param  mixed $result
     * @param  mixed $statusCode
     *
     * @return void
     */
    private function getJsonResponse(Array $result, $statusCode = 200) {
        $this->isEmpty($result, 'empty.result.given');
        $data = $result;
        $data['status'] = $this->getStatus($statusCode);
        //overriding status if status key found in result. 
        if (\array_key_exists('status', $result) === true) {
            $data['status'] = $result['status'];
        }

        return $this->body->withJson($data)->withStatus($statusCode);
    }

    /**
     * downloadResponse
     *
     * @param  mixed $result
     *
     * @return void
     */
    public function getDownloadResponse(Array $result) {
        $this->arrayKeyExists('file', $result, 'file.key.not.found');
        $this->isEmpty($result['file'], 'file.stream.is.empty.found');
        $this->arrayKeyExists('contentType', $result, 'contentType.not.found');
        $this->arrayKeyExists('fileName', $result, 'fileName.not.found');
        $this->arrayKeyExists('fileSize', $result, 'fileSize.not.found');

        return $this->body
            ->withHeader('Access-Control-Expose-Headers', 'Content-name')
            ->withHeader('Content-Description', 'File Download')
            ->withHeader('Content-Type', $result['contentType'])
            ->withHeader(
                'Content-Disposition',
                'attachment;filename="'. basename($result['fileName']) . '"'
            )
            ->withHeader('Expires', '0')
            ->withHeader('Cache-Control', 'must-revalidate')
            ->withHeader('Pragma', 'public')
            ->withHeader('Content-Length', $result['fileSize'])
            ->withHeader('Content-name', basename($result['fileName']))
            ->write($result['file']);
    }

    /**
     * rawResponse
     *
     * @param  mixed $result
     *
     * @return void
     */
    private function getRawResponse($result, $statusCode = 200) {
        $this->isEmpty($result, 'empty.result.given');
        $body = $result;
        if (\is_array($result) === true) {
            $body = \json_encode($result);
        }

        return $this->body->write($body)->withStatus($statusCode);
    }
}