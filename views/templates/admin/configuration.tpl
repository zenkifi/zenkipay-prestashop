{*
* 2007-2022 PrestaShop
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
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

    <div class="zenkipay-module-wrapper">
        <div class="zenkipay-module-header">
            <a href="https://zenki.fi/" target="_blank" rel="external">
                <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/logo.png" alt="Zenkipay" class="zenkipay-logo"/>
            </a>
            <span class="zenkipay-module-intro">{l s='Crypto payments for any ecommerce worldwide. Shoppers can pay you with any wallet and any coin.' mod='zenkipay'}</span>
            <a href="{$zenkipay_dashboard|escape:'htmlall':'UTF-8'}" rel="external" target="_blank" class="zenkipay-module-create-btn"><span>{l s='Create an account' mod='zenkipay'}</span></a>
        </div>
        <fieldset>
            <legend><img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/checks-icon.gif" alt="" />{l s='Technical check' mod='zenkipay'}</legend>
            <div class="conf">{$zenkipay_validation_title|escape:'htmlall':'UTF-8'}</div>
            <table cellspacing="0" cellpadding="0" class="zenkipay-technical">
                {if $zenkipay_validation} 
                {foreach from=$zenkipay_validation item=validation}
                <tr>
                    <td>
                        <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/{($validation['result']|escape:'htmlall':'UTF-8') ? 'tick' : 'close'}.png" alt="" style="height: 25px" />
                    </td>
                    <td>{$validation['name']|escape:'htmlall':'UTF-8'}</td>
                </tr>
                {/foreach} 
                {/if}
            </table>
        </fieldset>
        <br />      

        <form action="{$zenkipay_form_link|escape:'htmlall':'UTF-8'}" method="post">
            <fieldset class="zenkipay-settings">
                <legend><img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/technical-icon.gif" alt="" />{l s='Configuration' mod='zenkipay'}</legend>
                <div style="text-align: left; margin-left: 15px; margin-bottom: 10px;">    
                    <label style="float: none; width: auto;">{l s='Mode' mod='zenkipay'}</label>
                    <input id="zenkipay_mode_off" type="radio" name="zenkipay_mode" value="0" {if $zenkipay_configuration.ZENKIPAY_MODE == 0} checked="checked"{/if} /> {l s='Sandbox' mod='zenkipay'} 
                    <input id="zenkipay_mode_on" class="ml-5" type="radio" name="zenkipay_mode" value="1" {if $zenkipay_configuration.ZENKIPAY_MODE == 1} checked="checked"{/if} /> {l s='Live' mod='zenkipay'}
                </div>                
                <table cellspacing="0" cellpadding="0" class="zenkipay-settings">
                    <tr>
                        <td align="center" valign="middle" colspan="2">
                            <table cellspacing="0" cellpadding="0" class="innerTable">
                                <tr>
                                    <td>{l s='API key' mod='zenkipay'}</td>
                                    <td>
                                        <input
                                            autocomplete="off"
                                            type="text"
                                            id="zenkipay_api_key"
                                            name="zenkipay_api_key"
                                            value="{if $zenkipay_configuration.ZENKIPAY_API_KEY}{$zenkipay_configuration.ZENKIPAY_API_KEY|escape:'htmlall':'UTF-8'}{/if}"
                                        />
                                        <p>{l s='You must change this value if you switch your environment from production to testing.' mod='zenkipay'}</i></b></p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{l s='Secret key' mod='zenkipay'}</td>
                                    <td>
                                        <input
                                            autocomplete="off"
                                            type="password"
                                            id="zenkipay_secret_key"
                                            name="zenkipay_secret_key"
                                            value="{if $zenkipay_configuration.ZENKIPAY_SECRET_KEY}{$zenkipay_configuration.ZENKIPAY_SECRET_KEY|escape:'htmlall':'UTF-8'}{/if}"
                                        />
                                        <p>{l s='You must change this value if you switch your environment from production to testing.' mod='zenkipay'}</i></b></p>
                                    </td>                                    
                                </tr>
                                <tr>
                                    <td>{l s='Webhook signing secret' mod='zenkipay'}</td>
                                    <td>
                                        <input
                                            required
                                            autocomplete="off"
                                            type="password"
                                            id="zenkipay_webhook_signing_secret"
                                            name="zenkipay_webhook_signing_secret"
                                            value="{if $zenkipay_configuration.ZENKIPAY_WEBHOOK_SIGNING_SECRET}{$zenkipay_configuration.ZENKIPAY_WEBHOOK_SIGNING_SECRET|escape:'htmlall':'UTF-8'}{/if}"
                                        />
                                        <p>{l s='You can get this secret from your Zenkipay Dashboard: Configurations > Webhooks.' mod='zenkipay'}</i></b></p>
                                    </td>                                    
                                </tr>                               
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="td-noborder save"><input type="submit" class="button" name="SubmitZenkipay" value="{l s='Save configuration' mod='zenkipay'}" /></td>
                    </tr>
                </table>
            </fieldset>            
        </form>
        <div class="clear"></div>
    </div>
