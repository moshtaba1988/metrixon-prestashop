<div class="panel metrixon-risk-alert-detail">
  <div class="panel-heading">
    {l s='Risk alert detail' mod='metrixonriskalert'}
  </div>
  <p>
    <a class="btn btn-default" href="{$back_url|escape:'html':'UTF-8'}">
      <i class="icon-arrow-left"></i> {l s='Back to alerts' mod='metrixonriskalert'}
    </a>
  </p>

  <h2>{$alert.product_name|escape:'html':'UTF-8'}</h2>
  <p>
    <strong>{l s='SKU:' mod='metrixonriskalert'}</strong> {$alert.sku|default:'-'|escape:'html':'UTF-8'}
    {if $alert.variant_name}
      <br><strong>{l s='Variant:' mod='metrixonriskalert'}</strong> {$alert.variant_name|escape:'html':'UTF-8'}
    {/if}
  </p>

  <div class="row">
    <div class="col-md-6">
      <div class="panel">
        <div class="panel-heading">{l s='Compiled truth' mod='metrixonriskalert'}</div>
        <table class="table">
          <tbody>
          {foreach from=$alert.compiled_truth key=truth_key item=truth_value}
            <tr>
              <th>{$truth_key|escape:'html':'UTF-8'}</th>
              <td>
                {if is_array($truth_value)}
                  <pre>{json_encode($truth_value, JSON_PRETTY_PRINT)|escape:'html':'UTF-8'}</pre>
                {else}
                  {$truth_value|escape:'html':'UTF-8'}
                {/if}
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-md-6">
      <div class="panel">
        <div class="panel-heading">{l s='Recommended action' mod='metrixonriskalert'}</div>
        <p><strong>{$alert.action_recommendation.primary_cta|default:'Replenish now'|escape:'html':'UTF-8'}</strong></p>
        <p>{$alert.action_recommendation.rationale|default:''|escape:'html':'UTF-8'}</p>
        {if isset($alert.action_recommendation.deadline)}
          <p><strong>{l s='Deadline:' mod='metrixonriskalert'}</strong> {$alert.action_recommendation.deadline|escape:'html':'UTF-8'}</p>
        {/if}
        <hr>
        <p>
          <strong>{l s='Reason codes:' mod='metrixonriskalert'}</strong>
          {foreach from=$alert.reason_codes item=reason}
            <span class="label label-info">{$reason|escape:'html':'UTF-8'}</span>
          {/foreach}
        </p>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-heading">{l s='Why now timeline' mod='metrixonriskalert'}</div>
    <ul class="metrixon-risk-timeline">
      {foreach from=$alert.timeline item=event}
        <li>
          <strong>{$event.time|escape:'html':'UTF-8'}</strong>
          {$event.event|escape:'html':'UTF-8'}:
          {$event.value|escape:'html':'UTF-8'}
        </li>
      {foreachelse}
        <li>{l s='No timeline events recorded.' mod='metrixonriskalert'}</li>
      {/foreach}
    </ul>
  </div>

  <div class="panel">
    <div class="panel-heading">{l s='Threshold logic' mod='metrixonriskalert'}</div>
    <p>
      {l s='An active alert requires bestseller status, days until stockout at or below the urgency threshold, stockout before supplier lead time, estimated lost profit above the materiality floor, confidence above the minimum, fresh data, and minimum recent sales evidence.' mod='metrixonriskalert'}
    </p>
  </div>

  {if $alert.state == 'active'}
    <div class="panel">
      <div class="panel-heading">{l s='Lifecycle actions' mod='metrixonriskalert'}</div>
      <form method="post" class="form-inline">
        <input type="hidden" name="id_metrixon_risk_alert" value="{$alert.id_metrixon_risk_alert|intval}">
        <input type="hidden" name="state" value="resolved">
        <input type="hidden" name="reason" value="merchant_resolved">
        <button type="submit" name="submitRiskAlertState" class="btn btn-success">
          <i class="icon-check"></i> {l s='Mark resolved' mod='metrixonriskalert'}
        </button>
      </form>
      <hr>
      <form method="post" class="form-inline">
        <input type="hidden" name="id_metrixon_risk_alert" value="{$alert.id_metrixon_risk_alert|intval}">
        <input type="hidden" name="state" value="snoozed">
        <label>{l s='Snooze reason' mod='metrixonriskalert'}</label>
        <select name="reason" class="form-control" required>
          {foreach from=$feedback_reasons key=reason_key item=reason_label}
            <option value="{$reason_key|escape:'html':'UTF-8'}">{$reason_label|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
        <button type="submit" name="submitRiskAlertState" class="btn btn-warning">
          <i class="icon-clock-o"></i> {l s='Snooze' mod='metrixonriskalert'}
        </button>
      </form>
      <hr>
      <form method="post" class="form-inline">
        <input type="hidden" name="id_metrixon_risk_alert" value="{$alert.id_metrixon_risk_alert|intval}">
        <input type="hidden" name="state" value="dismissed">
        <label>{l s='Dismiss reason' mod='metrixonriskalert'}</label>
        <select name="reason" class="form-control" required>
          {foreach from=$feedback_reasons key=reason_key item=reason_label}
            <option value="{$reason_key|escape:'html':'UTF-8'}">{$reason_label|escape:'html':'UTF-8'}</option>
          {/foreach}
        </select>
        <button type="submit" name="submitRiskAlertState" class="btn btn-danger">
          <i class="icon-remove"></i> {l s='Dismiss' mod='metrixonriskalert'}
        </button>
      </form>
    </div>
  {/if}
</div>
