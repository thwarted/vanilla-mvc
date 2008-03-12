{capture assign=title}decorati | admin | template variables{/capture}{include file="common/doc-open.tpl" title=$title}

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
<head>
    <title>vanilla MVC | data inspector | template variables</title>
</head>
<body>

    <div class="admin_page">

        {if 0}<!--
        <table width="100%"><tbody>
        <tr>
            <td width="10%" align="left">{if $prevobjlink}<a href="{$prevobjlink}">&#8656; prev {$prevobjid}</a>{/if}</td>
            <td align="center"><h1>{$class}({$pkcol}={$pk})</h1></td>
            <td width="10%" align="right">{if $nextobjlink}<a href="{$nextobjlink}">{$nextobjid} next &#8658;</a>{/if}</td>
        </tr>
        </tbody></table>
        -->{/if}

        <table class='fields' cellspacing="0" style="clear: both;">
            <thead>
                <tr><th class="first">variable name</th><th>type</th><th>current value</th></tr>
            </thead>
            <tbody>
            {foreach from=$vars key=n item=v}
                {php}$x = $this->get_template_vars(); smarty_custom::$inspector_path = '$'.$x['n'];{/php}
                <tr class="{cycle values="odd,even"}">
                    <td class="name" valign="top">${$n}</td>
                    <td class="type" valign="top">{include file="type.tpl" v=$v}</td>
                    <td class="value">{include file="value.tpl v=$v}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>

</body>

</html>
