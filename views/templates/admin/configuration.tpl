{*
* 2007-2023 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if $sync_successfully === true}
    <div class="znk-notice success"><img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/tick.png" alt="" height="14"
            width="14" /> {$sync_message|escape:'htmlall':'UTF-8'}</div>
{elseif $sync_successfully === false}
    <div class="znk-notice error">{$sync_message|escape:'htmlall':'UTF-8'}</div>
{/if}

<form action="{$zenkipay_form_link|escape:'htmlall':'UTF-8'}" method="post" class="form-table">
    <div class="znk-admin-container">
        <div class="znk-header">
            <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/logo-white.png" alt="Zenkipay" class="znk-img" />
            <div class="znk-copy">
                <p>Your shopper can pay with cryptos, any wallet, any coin! Transacion 100% secured.</p>
            </div>
        </div>
        <div class="znk-form-container">
            <p class="instructions">To set up quickly and easily, enter the synchronization code from your Zenkipay
                portal.
            </p>
            <hr>

            <table cellspacing="0" cellpadding="0" class="zenkipay-settings">
                <tr>
                    <th>{l s='Enable test mode' mod='zenkipay'}</th>
                    <td class="forminp">
                        <label for="zenkipay_mode">
                            <input id="zenkipay_mode" type="checkbox" name="zenkipay_mode"
                                {if $zenkipay_configuration.ZENKIPAY_MODE == true} checked="checked" {/if} />
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>{l s='Enter sync code' mod='zenkipay'}</th>
                    <td class="forminp">
                        <input type="text" id="zenkipay_sync_code" name="zenkipay_sync_code"
                            value="{if $zenkipay_configuration.ZENKIPAY_SYNC_CODE}{$zenkipay_configuration.ZENKIPAY_SYNC_CODE|escape:'htmlall':'UTF-8'}{/if}" />
                        {if $zenkipay_configuration.ZENKIPAY_SYNC_CODE}
                            <p class="description">{$zenkipay_env|escape:'htmlall':'UTF-8'}</p>
                        {/if}
                    </td>
                </tr>
            </table>


        </div>
    </div>
    <input type="submit" class="button-primary" name="SubmitZenkipay" value="{l s='Save changes' mod='zenkipay'}" />
</form>

<script type="text/javascript">
    $(document).ready(function() {
        console.log('ZENKIPAY');
        var element = document.getElementById('zenkipay_sync_code');
        var maskOptions = {
            mask: 'a\\-******',
            lazy: false, // make placeholder always visible
            placeholderChar: 'X', // defaults to '_'
            prepare: function(str) {
                return str.toUpperCase();
            },
        };
        var mask = IMask(element, maskOptions);
    });
</script>