<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Controllers
 * @subpackage Api
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Benjamin Cremer
 */

use Shopware\Components\Api\Exception as ApiException;

/**
 * REST-API Error Handler
 */
class Shopware_Controllers_Api_Index extends Shopware_Controllers_Api_Rest
{
    public function invalidAction()
    {
        $this->View()->assign(array('success' => false, 'message' => 'Invalid method'));
    }

    public function noAuthAction()
    {
        $this->View()->assign(array('success' => false, 'message' => 'Invalid or missing auth'));
    }

    /**
     * Error Action to catch all Exceptions and return valid json response
     */
    public function errorAction()
    {
        $error = $this->Request()->getParam('error_handler');

        /** @var \Exception $exception  */
        $exception = $error->exception;

        if ($exception instanceof Enlight_Controller_Exception) {
            $this->Response()->setHttpResponseCode(404);

            $this->View()->assign(array(
                'success' => false,
                'message' => 'Resource not found'
            ));

            return;
        }

        if ($exception instanceof ApiException\PrivilegeException) {
            $this->Response()->setHttpResponseCode(403);

            $this->View()->assign(array(
                'success' => false,
                'message' => $exception->getMessage()
            ));

            return;
        }

        if ($exception instanceof ApiException\NotFoundException) {
            $this->Response()->setHttpResponseCode(404);

            $this->View()->assign(array(
                'success' => false,
                'message' => $exception->getMessage()
            ));

            return;
        }

        if ($exception instanceof ApiException\ParameterMissingException) {
            $this->Response()->setHttpResponseCode(400);

            $this->View()->assign(array(
                'success' => false,
                'code'    => 400,
                'message' => 'A required parameter is missing'
            ));

            return;
        }

        if ($exception instanceof ApiException\CustomValidationException) {
            $this->Response()->setHttpResponseCode(400);

            $this->View()->assign(array(
                'success' => false,
                'message' => $exception->getMessage()
            ));

            return;
        }

        if ($exception instanceof ApiException\ValidationException) {
            /** @var \Shopware\Components\Api\Exception\ValidationException $exception */
            $this->Response()->setHttpResponseCode(400);

            $errors = array();
            /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
            foreach ($exception->getViolations() as $violation) {
                $errors[] = sprintf(
                    '%s: %s',
                    $violation->getPropertyPath(),
                    $violation->getMessage()
                );
            }

            $this->View()->assign(array(
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $errors,
            ));

            return;
        }

        $this->Response()->setHttpResponseCode(500);
        $debug = true;
        if ($debug) {
            $this->View()->assign(array('success' => false, 'message' => 'Errormesage: ' . $exception->getMessage()));
        } else {
            $this->View()->assign(array('success' => false, 'message' => 'Unknown Error'));
        }
    }
}
