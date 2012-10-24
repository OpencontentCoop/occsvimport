<script language="JavaScript1.2" type="text/javascript">
menuArray['csvimport'] = new Array();
menuArray['csvimport']['depth'] = 1; // this is a first level submenu of ContextMenu
menuArray['csvimport']['elements'] = new Array();
</script>

 <hr/>
    <a id="menu-csvimport" class="more" href="#" onmouseover="ezpopmenu_showSubLevel( event, 'csvimport', 'menu-csvimport' ); return false;">{'CSV import'|i18n( 'extension/occsvimport/popupmenu' )}</a>

{* Import OOo / OASIS document *}
<form id="menu-form-import-csv" method="post" action={"/csvimport/import/"|ezurl}>
  <input type="hidden" name="NodeID" value="%nodeID%" />
  <input type="hidden" name="ObjectID" value="%objectID%" />
</form>


{* Replace OOo / OASIS document *}
<form id="menu-form-export-csv" method="post" action={"/csvimport/export/"|ezurl}>
  <input type="hidden" name="ImportType" value="export" />
  <input type="hidden" name="NodeID" value="%nodeID%" />
  <input type="hidden" name="ObjectID" value="%objectID%" />
</form>
