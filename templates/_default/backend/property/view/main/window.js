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
 * @package    Property
 * @subpackage View
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author shopware AG
 */

//{namespace name=backend/property/view/main}
//{block name="backend/property/view/main/window"}
Ext.define('Shopware.apps.Property.view.main.Window', {
    extend: 'Enlight.app.Window',
    alias : 'widget.property-main-window',
    title : '{s name=title}Article properties{/s}',
    width: 860,
    height: 484, // 16:9 'cause shopware should be HDTV Compatible :P
    stateful: true,
    stateId: 'shopware-property-main-window',

    layout: {
        type: 'hbox',
        pack: 'start',
        align: 'stretch'
    },

    /**
     * Initializes the component and builds up the main interface
     *
     * @public
     * @return void
     */
    initComponent: function() {
        var me = this;

        me.items = [{
            xtype: 'property-main-groupTree',
            groupStore: me.groupStore,
            flex: 3
        }, {
            xtype: 'property-main-filterOptionGrid',
            filterOptionStore: me.filterOptionStore,
            flex: 2
        }, {
            xtype: 'property-main-valueGrid',
            valueStore: me.valueStore,
            flex: 2,
            disabled: true
        }];

        me.callParent(arguments);
    }
});
//{/block}
