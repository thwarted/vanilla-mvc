{if is_object($v)}
    {if $v instanceof ModelCollection}
        {assign var=l value=$v|objlink}
        {assign var=ix value="id"|uniqid}
        <span onclick="return toggleCollection('{$ix}');" class="collection" style="float: left; padding-right: 10px;">{$l}</span>
        <ol id="{$ix}" style="display: none; clear: both; margin-bottom: 0px;">
            {foreach from=$v item=o}
                {assign var=s value="no"}
                {capture assign=x}{$f}[{$o->id}]{/capture}{$x|notefieldname}
                {capture assign=x}{$f}/{$o->id}{/capture}{$x|noteobjlink}
                <li>{include file="value.tpl v=$o styleit=$s}</li>
            {/foreach}
        </ol>
        {assign var=s value=""}
    {elseif $v instanceof url}
        {$v}
    {else}
        {if $styleit === 'no'}
            {$v|objlink}
        {else}
            {$v|objlink|style:"float: left; padding-right: 10px;"}
        {/if}
        {if preg_match('/_?image$/', $f) && $v instanceof image}
            {$v->transform('fit','50by50')}
            <img align="middle" src="{$v->url}" border="0" />
        {/if}
    {/if}
{elseif $f == 'url'}
    {if is_array($v)}
        {assign var=r value=$smarty.server.uribase}
        <dl style="margin-top: 5px; margin-bottom: 5px;">
        {foreach from=$v key=n item=u}
            <dt style="min-width: 10em; font-weight: bold; padding-right: 1em; text-align: left;">-&gt;url.{$n}</dt>
            <dd><a class="urllinklist" href="{$u}">{$u|replace:$smarty.server.uribase:'/'}</a></dd>
        {/foreach}
        </dl>
    {else}
        <a class="urllinklist" href="{$v}">{$v|replace:$smarty.server.uribase:'/'}</a></dd>
    {/if}
{elseif $f === 'data' && $obj instanceof imgdata}
    {assign var=i value=$obj|getimageobj}
    {if $i}
        <div style="float: left; margin-right: 2em; font-size: 80%;">
            {$v|strlen|number_format} bytes of data
            <br />
            {$i->mime_type}
        </div>
        {$i->transform('fit','50by50')}
        <img align="middle" src="{$i->url}" border="0" />
    {else}
        {$v|strlen|number_format} bytes of data
    {/if}
{elseif preg_match('/html/', $f) && is_string($v)}
    <pre>{$v|htmlentities}</pre>
{elseif preg_match('/(^date_|^last_login$|_at$|_date$)/', $f) && is_integer($v)}
    <span class="date">{$v} {if $v}({$v|date_format:"%Y-%m-%d %T"}){/if}</span>
{else}
    {if !isset($v)}
        <span class="null">NULL</span>
    {elseif is_array($v)}
        {assign var=ix value="id"|uniqid}
        <span onclick="return toggleCollection('#{$ix}');" class="collection" style="float: left; padding-right: 10px;">array ({$v|@count} members)</span>
        <dl id="{$ix}" style="display: none; clear: both; margin-bottom: 0px;">
        {foreach from=$v key=n item=x}
            <dt><span style="color: red;">.{$n}</span> =&gt;</dt>
            <dd style="padding-left: 1em;">{include file="value.tpl" v=$x}</dd>
        {/foreach}
        </dl>
    {else}
        <pre>{$v|@printr|replace:"\n":"<br/>"|wordwrap}</pre>
    {/if}
{/if}
