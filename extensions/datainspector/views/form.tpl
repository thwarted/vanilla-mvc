{capture assign=title}decorati | admin | view object {$obj|@get_class} {$pk} {/capture}{include file="common/doc-open.tpl" title=$title}
<div class="admin_page">

    <table width="100%"><tbody>
    <tr>
        <!-- td width="10%" align="left">{if $prevobjlink}<a href="{$prevobjlink}">&#8656; prev {$prevobjid}</a>{/if}</td -->
        <td align="center"><h1>form {$class} - {$name}</h1></td>
        <!-- td width="10%" align="right">{if $nextobjlink}<a href="{$nextobjlink}">{$nextobjid} next &#8658;</a>{/if}</td -->
    </tr>
    </tbody></table>

    <h2>Fields</h2>
    <table class='fields' cellspacing="0">
        <thead>
            <tr><th class="first">field name</th><th>type</th><th>label</th><th>default value</th><th>verified with</th></tr>
        </thead>
        <tbody>
        {foreach from=$form key=name item=f}
            <tr class="{cycle values="odd,even"}">
                <td class="name" valign="top">.{$name}</td>
                <td class="type" valign="top">{$f->type()} {if $f->has_multiple_values()} takes multiple values {/if}</td>
                <td class="type">{$f->label_str()}</td>
                <td class="type">{$f->value()|@printr}</td>
                <td class="type">{$f->verified_with()}</td>
            </tr>
        {/foreach}
        </tbody>
    </table>

</div>

{include file="common/doc-close.tpl"}
