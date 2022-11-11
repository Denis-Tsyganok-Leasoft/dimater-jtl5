<div>
    <input type="hidden" id="ginger_payment" name="ginger_payment" value="ginger_ideal" />
    <select name="issuer" id="issuer" required>
        <option value="">{lang key='ideal_choose_bank' section='emspay_jtl5'}</option>
        {foreach from=$issuers item=issuer}
            <option value="{$issuer.id}">{$issuer.name}</option>
        {/foreach}
    </select>

</div>



