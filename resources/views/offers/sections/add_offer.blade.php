<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/offers.text_red_star_required')))</p>
<div align="center"><form id="compose" action="?new_offer=1" name="compose" method="post">
@csrf
<div class="nx-fgrid nx-fgrid--flat">
<div class="nx-ffull nx-colhead nx-center">{{ __('legacy/offers.text_offers_open_to_all')}}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_type')}}<font color=red>*</font></b></div><div class="nx-fcell"> @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($add_offer['typeOptions'] ?? ''))</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_title')}}<font color=red>*</font></b></div><div class="nx-fcell"><input type=text name=name style="width: 99%;" /></div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_post_or_photo')}}</b></div><div class="nx-fcell"><input type=text name=picture style="width: 99%;"><br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/offers.text_link_to_picture')))</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_description')}}<b><font color=red>*</font></div><div class="nx-fcell">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($add_offer['bbcodeEditor'] ?? ''))
</div>
<div class="nx-ffull nx-toolbox nx-center"><input id=qr type=submit class=btn value={{ __('legacy/offers.submit_add_offer')}} ></div>
</div></form><br />
