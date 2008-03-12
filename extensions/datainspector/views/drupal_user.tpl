{capture assign=title}decorati | admin | view drupal node {$type} {$nid} {/capture}{include file="common/doc-open.tpl" title=$title}
<div class="admin_page">

    <table width="100%"><tbody>
    <tr>
        <td width="10%" align="left">{if $prevobjlink}<a href="{$prevobjlink}">&#8656; prev {$prevobjid}</a>{/if}</td>
        <td align="center"><h1>drupal node {$type} {$nid}</h1></td>
        <td width="10%" align="right">{if $nextobjlink}<a href="{$nextobjlink}">{$nextobjid} next &#8658;</a>{/if}</td>
    </tr>
    </tbody></table>

    <h2>Drupal Node Fields <span style="font-size: .5em;">(drupal defined)</span></h2>
    <table class='fields' cellspacing="0" style="clear: both;">
        <thead>
            <tr><th class="first">field name</th><th>type</th><th>current value</th></tr>
        </thead>
        <tbody>
        {foreach from=$node|get_object_vars key=f item=j}
            {if is_numeric($f)}{continue}{/if}
            {if $f === 'data'}{continue}{/if}
            {$f|notefieldname}
            <tr class="{cycle values="odd,even"}">
            <td class="name" valign="top">-&gt;{$f}</td>
            <td class="type" valign="top">{include file="type.tpl" v=$node->$f}</td>
            <td class="value">{include file="drupal_value.tpl v=$node->$f}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>

</div>

{include file="common/doc-close.tpl"}
