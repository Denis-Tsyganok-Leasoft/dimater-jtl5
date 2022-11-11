<div style="display: flex; flex-direction: column;">
    <input type="hidden" id="ginger_payment" name="ginger_payment" value="ginger_afterpay" />
    <select name="gender" id="gender" style="max-width: 150px" required>
        <option value="">{lang key='afterpay_chose_gender' section='emspay_jtl5'}</option>
        <option value="male">{lang key='afterpay_gender_male' section='emspay_jtl5'}</option>
        <option value="female">{lang key='afterpay_gender_female' section='emspay_jtl5'}</option>
    </select>
    <div>
        {lang key='afterpay_enter_dob' section='emspay_jtl5'}
        <input type="date" id="bday" name="bday" style="max-width: 150px" required>
    </div>
    <div>
        <input type="checkbox" id="toc" name="toc">{lang key='afterpay_i_accept' section='emspay_jtl5'} <a href="https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden">{lang key='afterpay_toc' section='emspay_jtl5'}</a>
    </div>
</div>

