<?php

class PrefixedCSVImportHandler extends CSVImportHandler
{
    private $remotePrefix;

    private function getRemotePrefix()
    {
        if ($this->remotePrefix === null) {
            $parent = eZContentObjectTreeNode::fetch($this->options->attribute('parent_node_id'));
            $this->remotePrefix = eZCharTransform::instance()->transformByGroup($parent->attribute('name'), 'urlalias') . '-';
        }

        return $this->remotePrefix;
    }

    /**
     * @param SQLICSVRow $row
     */
    public function process($row)
    {
        $newRow = array();
        foreach ($row as $key => $value) {
            if ($key == 'remoteId') {
                $value = $this->getRemotePrefix() . $value;
            }
            $newRow[$key] = $value;
        }

        $newCsvRow = new SQLICSVRow($newRow);

        parent::process($newCsvRow);
    }
}
