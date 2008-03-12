{strip}
{assign var=t value=$v|@gettype|strtolower}
{if $t === 'array'}
    {assign var=label value="iterate using foreach, or access members with .field"}
{elseif $t === 'object'}
    {if $v instanceof ModelCollection}
        {assign var=t value="array"}
        {assign var=label value="iterate using foreach"}
    {elseif $v instanceof url}
        {assign var=t value="url"}
        {assign var=label value="print directly"}
    {else}
        {assign var=label value="access subfields with -&gt;field"}
    {/if}
{elseif $t === 'null'}
    {assign var=label value="no value, will print as empty string"}
{elseif preg_match('/(^date_|^last_login$|_at$|_date$)/', $f) && is_integer($v)}
    {assign var=label value="format using date_format"}
    {assign var=t value="date/time"}
{else}
    {assign var=label value="print directly"}
{/if}
{/strip}<abbr title="{$label}">{$t}</abbr>
