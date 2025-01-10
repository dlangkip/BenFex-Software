{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/mpesa">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">{Lang::T('M-Pesa Payment Gateway')}</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Consumer Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="consumer_key" name="consumer_key" 
                                value="{$_c['mpesa_consumer_key']}">
                            <small class="form-text text-muted">Get your Consumer Key from Safaricom Daraja Portal</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Consumer Secret</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="consumer_secret" name="consumer_secret" 
                                value="{$_c['mpesa_consumer_secret']}">
                            <small class="form-text text-muted">Get your Consumer Secret from Safaricom Daraja Portal</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Shortcode</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="shortcode" name="shortcode" 
                                value="{$_c['mpesa_shortcode']}">
                            <small class="form-text text-muted">Your M-Pesa Paybill or Till Number</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Passkey</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="passkey" name="passkey" 
                                value="{$_c['mpesa_passkey']}">
                            <small class="form-text text-muted">Get your Passkey from Safaricom Daraja Portal</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Environment</label>
                        <div class="col-md-6">
                            <select class="form-control" name="env">
                                <option value="sandbox" {if $_c['mpesa_env'] eq 'sandbox'}selected{/if}>Sandbox (Testing)</option>
                                <option value="live" {if $_c['mpesa_env'] eq 'live'}selected{/if}>Live</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light" type="submit">{Lang::T('Save Changes')}</button>
                        </div>
                    </div>

                    <pre>/ip hotspot walled-garden
add dst-host=safaricom.co.ke
add dst-host=*.safaricom.co.ke</pre>
                    <small class="form-text text-muted">{Lang::T('Set Telegram Bot to get any error and notification')}</small>

                    <div class="form-group">
                        <label class="col-md-2 control-label">Callback URL</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="callback_url" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let callbackInput = document.getElementById('callback_url');
var fullURL = window.location.href;
callbackInput.value = "https://" + fullURL.split('/')[2] + "/index.php?_route=callback/mpesa";
</script>

{include file="sections/footer.tpl"}