<h1>{$page->title}</h1>
<table>
	{foreach $sitemaps as $sitemap}
		{if $last_type != $sitemap->type}
			{assign var=last_type value=$sitemap->type}
			<tr>
				<td>
					&nbsp;
				</td>
			</tr>
			<tr>
				<td>
					{$sitemap->type}
				<td>
			</tr>
		{/if}
		<tr>
			<td>
				<a title="{$sitemap->type} - {$sitemap->name}" href="{$smarty.const.WWW_TOP}{$sitemap->loc}">{$sitemap->name}</a>
			</td>
		</tr>
	{/foreach}
</table>
