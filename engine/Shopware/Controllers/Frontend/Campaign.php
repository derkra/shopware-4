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
 * @subpackage Campaign
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     $Author$
 */

/**
 * todo@all: Documentation
 */
class Shopware_Controllers_Frontend_Campaign extends Enlight_Controller_Action
{
    public function indexAction()
    {
        if (Shopware()->Shop()->get('esi')) {
            $getMetaFields = Shopware()->Db()->fetchRow('
                SELECT seo_keywords, seo_description, name FROM s_emotion WHERE id = ?
            ', array($this->Request()->getParam('emotionId')));

            //$this->View()->extendsBlock('frontend_index_header_title', $getMetaFields['name'], null);
            $this->View()->assign('sBreadcrumb', array(0 => array('name' => $getMetaFields['name'])));
            $this->View()->assign('seo_keywords', $getMetaFields['seo_keywords']);
            $this->View()->assign('seo_description', $getMetaFields['seo_description']);

            $this->View()->assign('emotionId', intval($this->Request()->getParam('emotionId')));
        } else {
            // @deprecated - support for shopware 3.x campaigns
            $campaignId = (int)$this->Request()->sCampaign;
            if (empty($$campaignId)) {
                return $this->forward('index', 'index');
            }
            $campaign = Shopware()->Modules()->Marketing()->sCampaignsGetDetail($campaignId);
            if (empty($campaign['id'])) {
                return $this->forward('index', 'index');
            }
            $this->View()->loadTemplate("frontend/campaign/old.tpl");
            $this->View()->sCampaign = $campaign;
        }
    }
}