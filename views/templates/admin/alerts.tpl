<div class="panel metrixon-risk-panel">
  <div class="panel-heading">
    <i class="icon-warning-sign"></i> {l s='Metrixon Risk Alerts' mod='metrixonriskalert'}
  </div>

  <div class="alert alert-info">
    {l s='Only BESTSELLER_STOCKOUT_URGENT alerts are generated in this pilot module. Alerts are deterministic and include numeric provenance.' mod='metrixonriskalert'}
  </div>

  <div class="clearfix">
    <a class="btn btn-primary pull-right" href="{$run_url|escape:'htmlall':'UTF-8'}">
      <i class="icon-refresh"></i> {l s='Run evaluation now' mod='metrixonriskalert'}
    </a>
  </div>

  <hr>

  {if empty($alerts)}
    <p class="text-muted">{l s='No alerts have been generated yet.' mod='metrixonriskalert'}</p>
  {else}
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>{l s='SKU / Product' mod='metrixonriskalert'}</th>
            <th>{l s='Severity' mod='metrixonriskalert'}</th>
            <th>{l s='State' mod='metrixonriskalert'}</th>
            <th>{l s='Days until stockout' mod='metrixonriskalert'}</th>
            <th>{l s='Estimated lost profit' mod='metrixonriskalert'}</th>
            <th>{l s='Confidence' mod='metrixonriskalert'}</th>
            <th>{l s='Updated' mod='metrixonriskalert'}</th>
            <th class="text-right">{l s='Action' mod='metrixonriskalert'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$alerts item=alert}
            <tr>
              <td>
                <strong>{$alert.product_name|escape:'htmlall':'UTF-8'}</strong>
                {if $alert.variant_name}<br><span class="text-muted">{$alert.variant_name|escape:'htmlall':'UTF-8'}</span>{/if}
                {if $alert.sku}<br><code>{$alert.sku|escape:'htmlall':'UTF-8'}</code>{/if}
              </td>
              <td><span class="label label-{if $alert.severity == 'critical'}danger{else}warning{/if}">{$alert.severity|escape:'htmlall':'UTF-8'}</span></td>
              <td>{$alert.state|escape:'htmlall':'UTF-8'}</td>
              <td>{$alert.last_days_until_stockout|string_format:'%.1f'}</td>
              <td>{$alert.last_estimated_lost_profit|string_format:'%.2f'}</td>
              <td>{($alert.compiled_truth.confidence_score * 100)|string_format:'%.0f'}%</td>
              <td>{$alert.date_upd|escape:'htmlall':'UTF-8'}</td>
              <td class="text-right">
                <a class="btn btn-default" href="{$alert.detail_url|escape:'htmlall':'UTF-8'}">
                  <i class="icon-search"></i> {l s='Details' mod='metrixonriskalert'}
                </a>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {/if}
</div>
