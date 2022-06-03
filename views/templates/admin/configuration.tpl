{*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
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
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
                {if $zenkipay_validation} {foreach from=$zenkipay_validation item=validation}
                <tr>
                    <td>
                        <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/{($validation['result']|escape:'htmlall':'UTF-8') ? 'tick' : 'close'}.png" alt="" style="height: 25px" />
                    </td>
                    <td>{$validation['name']|escape:'htmlall':'UTF-8'}</td>
                </tr>
                {/foreach} {/if}
            </table>
        </fieldset>
        <br />

        {if $zenkipay_error}
        <fieldset>
            <legend>{l s='Errors' mod='zenkipay'}</legend>
            <table cellspacing="0" cellpadding="0" class="zenkipay-technical">
                <tbody>
                    {foreach from=$zenkipay_error item=error}
                    <tr>
                        <td><img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/close.png" alt="" style="height: 25px" /></td>
                        <td>{$error|escape:'htmlall':'UTF-8'}</td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
        </fieldset>
        <br />
        {/if}

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
                                    <td align="left" valign="middle">{l s='Sandbox Zenkipay key' mod='zenkipay'}</td>
                                    <td align="left" valign="middle">
                                        <input
                                            autocomplete="off"
                                            type="text"
                                            id="zenkipay_public_key_test"
                                            name="zenkipay_public_key_test"
                                            value="{if $zenkipay_configuration.ZENKIPAY_PUBLIC_KEY_TEST}{$zenkipay_configuration.ZENKIPAY_PUBLIC_KEY_TEST|escape:'htmlall':'UTF-8'}{/if}"
                                        />
                                    </td>
                                    <td width="15"></td>
                                    <td width="15" class="vertBorder"></td>
                                    <td align="left" valign="middle">{l s='Live Zenkipay key' mod='zenkipay'}</td>
                                    <td align="left" valign="middle">
                                        <input
                                            autocomplete="off"
                                            type="text"
                                            id="zenkipay_public_key_live"
                                            name="zenkipay_public_key_live"
                                            value="{if $zenkipay_configuration.ZENKIPAY_PUBLIC_KEY_LIVE}{$zenkipay_configuration.ZENKIPAY_PUBLIC_KEY_LIVE|escape:'htmlall':'UTF-8'}{/if}"
                                        />
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

<script type="text/javascript">    
    $(document).ready(function() {             
        var handleDisableInputs = function () {
            if( $('#zenkipay_mode_off').is(':checked') ){
                $('#zenkipay_public_key_test').prop('readonly', false); 
                $('#zenkipay_public_key_live').prop('readonly', true); 
            } else{
                $('#zenkipay_public_key_live').prop('readonly', false); 
                $('#zenkipay_public_key_test').prop('readonly', true); 
            }
        }        

        $('input[name=zenkipay_mode]').on('change', function() {            
            handleDisableInputs();
        });

        handleDisableInputs();
    });
</script>    