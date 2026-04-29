<div class="panel metrixon-risk-panel">
  <div class="panel-heading">
    <i class="icon-cogs"></i> {l s='Metrixon Risk Alert Settings' mod='metrixonriskalert'}
  </div>

  <form method="post" action="{$form_action|escape:'html':'UTF-8'}" class="form-horizontal">
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Bestseller mode' mod='metrixonriskalert'}</label>
      <div class="col-lg-4">
        <select name="METRIXON_RA_BESTSELLER_MODE" class="form-control">
          <option value="percentile" {if $config.METRIXON_RA_BESTSELLER_MODE == 'percentile'}selected{/if}>{l s='Top percentile with top-N fallback' mod='metrixonriskalert'}</option>
          <option value="top_n" {if $config.METRIXON_RA_BESTSELLER_MODE == 'top_n'}selected{/if}>{l s='Top N only' mod='metrixonriskalert'}</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Bestseller percentile' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" min="1" max="100" name="METRIXON_RA_BESTSELLER_PERCENTILE" value="{$config.METRIXON_RA_BESTSELLER_PERCENTILE|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Top N fallback' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" min="1" name="METRIXON_RA_BESTSELLER_TOP_N" value="{$config.METRIXON_RA_BESTSELLER_TOP_N|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Urgency threshold days' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" step="0.1" min="0" name="METRIXON_RA_URGENCY_DAYS" value="{$config.METRIXON_RA_URGENCY_DAYS|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Materiality floor' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" step="0.01" min="0" name="METRIXON_RA_MATERIALITY_FLOOR" value="{$config.METRIXON_RA_MATERIALITY_FLOOR|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Confidence minimum' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" step="0.01" min="0" max="1" name="METRIXON_RA_CONFIDENCE_MINIMUM" value="{$config.METRIXON_RA_CONFIDENCE_MINIMUM|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Cooldown hours' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" min="1" name="METRIXON_RA_COOLDOWN_HOURS" value="{$config.METRIXON_RA_COOLDOWN_HOURS|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Max active alerts' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" min="1" name="METRIXON_RA_MAX_ACTIVE_ALERTS" value="{$config.METRIXON_RA_MAX_ACTIVE_ALERTS|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Freshness max minutes' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" min="1" name="METRIXON_RA_FRESHNESS_MAX_MINUTES" value="{$config.METRIXON_RA_FRESHNESS_MAX_MINUTES|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Minimum sale events' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" min="1" name="METRIXON_RA_MIN_SALE_EVENTS" value="{$config.METRIXON_RA_MIN_SALE_EVENTS|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Default lead time days' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" step="0.1" min="1" name="METRIXON_RA_DEFAULT_LEAD_TIME_DAYS" value="{$config.METRIXON_RA_DEFAULT_LEAD_TIME_DAYS|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Default margin percent' mod='metrixonriskalert'}</label>
      <div class="col-lg-2"><input type="number" step="0.1" min="0" max="100" name="METRIXON_RA_DEFAULT_MARGIN_PERCENT" value="{$config.METRIXON_RA_DEFAULT_MARGIN_PERCENT|escape:'html':'UTF-8'}"></div>
    </div>
    <div class="panel-footer">
      <button type="submit" name="submitMetrixonRiskSettings" class="btn btn-primary pull-right">
        <i class="process-icon-save"></i> {l s='Save' mod='metrixonriskalert'}
      </button>
    </div>
  </form>
</div>

<div class="panel">
  <div class="panel-heading">{l s='Cron URL' mod='metrixonriskalert'}</div>
  <p>{l s='Schedule this URL to run the polling-based risk evaluation.' mod='metrixonriskalert'}</p>
  <pre>{$cron_url|escape:'html':'UTF-8'}</pre>
</div>

<div class="panel">
  <div class="panel-heading">{l s='Adapter capabilities' mod='metrixonriskalert'}</div>
  <table class="table">
    <tbody>
      {foreach from=$capabilities key=capability item=value}
        <tr>
          <th>{$capability|escape:'html':'UTF-8'}</th>
          <td>
            {if is_bool($value)}
              {if $value}{l s='Yes' mod='metrixonriskalert'}{else}{l s='No' mod='metrixonriskalert'}{/if}
            {elseif is_array($value)}
              <pre>{json_encode($value, JSON_PRETTY_PRINT)|escape:'html':'UTF-8'}</pre>
            {else}
              {$value|escape:'html':'UTF-8'}
            {/if}
          </td>
        </tr>
      {/foreach}
    </tbody>
  </table>
</div>
