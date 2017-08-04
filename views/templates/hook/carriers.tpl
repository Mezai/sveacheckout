<div class="card carrier-container">    
        <div class="card-block">    
            <div class="col-md-12"> 
                <div class="delivery-options-list">
    {if $delivery_options|count}
       <form action="{$link->getModuleLink('sveacheckout', 'carrier')}" 
        method="post"
      >
        <div class="form-fields">
          {block name='delivery_options'}
            <div class="delivery-options">
              {foreach from=$delivery_options item=carrier key=carrier_id}
                  <div class="row delivery-option">
                    <div class="col-sm-1">
                      <span class="pull-xs-left">
                        <button type="submit" class="btn btn-primary btn-md" name="delivery_option[{$id_address}]" id="delivery_option_{$carrier.id_carrier}" value="{$carrier.id_carrier|cat:","}"{if $delivery_option == $carrier_id} checked{/if}>
                        {l s='Select' d='Shop.Theme.Checkout'}
                        </button>
                        <span></span>
                      </span>
                    </div>
                    <label for="delivery_option_{$carrier.id_carrier}" class="col-sm-11 delivery-option-2">
                      <div class="row">
                        <div class="col-sm-5 col-xs-12">
                          <div class="row">
                           
                              <span class="h6 carrier-name">{$carrier.name}</span>
                          </div>
                        </div>
                        <div class="col-sm-4 col-xs-12">
                          <span class="carrier-delay">{$carrier.delay}</span>
                        </div>
                        <div class="col-sm-3 col-xs-12">
                          <span class="carrier-price"></span>
                        </div>
                      </div>
                    </label>
                    <div class="col-md-12 carrier-extra-content"{if $delivery_option != $carrier_id} style="display:none;"{/if}>
                    </div>
                    <div class="clearfix"></div>
                  </div>
              {/foreach}
            </div>
          {/block}
          <div class="order-options">
            {if $recyclablePackAllowed}
              <label>
                <input type="checkbox" name="recyclable" value="1" {if $recyclable} checked {/if}>
                <span>{l s='I would like to receive my order in recycled packaging.' d='Shop.Theme.Checkout'}</span>
              </label>
            {/if}
    
          </div>
        </div>
      </form>
    {else}
      <p class="alert alert-danger">{l s='Unfortunately, there are no carriers available for your delivery address.' d='Shop.Theme.Checkout'}</p>
    {/if}
      </div>
    </div>  
  </div>
</div>