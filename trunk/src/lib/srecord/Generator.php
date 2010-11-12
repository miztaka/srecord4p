<?php

/**
 * generate entity class
 *
 * @package teeple
 */
class Srecord_Generator {

    /**
     * @var Logger
     */
    protected $log;
    
    protected $forceUpdate = TRUE;
    
    /**
     * constructor
     */
    public function __construct() {
        //$this->log = LoggerManager::getLogger(get_class($this));
    }

    /**
     * generate entity
     * 
     */
    public function execute() {
        
        // connect to salesforce
        $client = Srecord_Schema::getClient(); 
        
        // get object definition
        $objectTypes = array();
        $global = $client->describeGlobal();
        foreach($global->sobjects as $def) {
            $objectTypes[] = $def->name;
        }
        
        $objectDefs = array();
        $offset = 0;
        $length = 100;
        $max = count($objectTypes);
        while($offset < $max) {
            $part = $client->describeSObjects(array_slice($objectTypes, $offset, $length));
            foreach($part as $one) { // TODO arrayの+でうまくいかなかった。。
                $objectDefs[] = $one;
            }
            $offset += $length;
        }
        
        // generate class file
        foreach($objectDefs as $def) {
            $this->manipMeta($def);
            $this->generate($def);
        }
    }
    
    private function manipMeta($metaObj)
    {
        if (isset($metaObj->childRelationships)) {
            if (! is_array($metaObj->childRelationships)) {
                $metaObj->childRelationships = array($metaObj->childRelationships);
            }
            $childs = array();
            foreach ($metaObj->childRelationships as $one) {
                if (isset($one->relationshipName)) {
                    $childs[$one->relationshipName] = $one;
                }
            }
            $metaObj->childRelationships = $childs;
        }
        if (isset($metaObj->fields)) {
            if (! is_array($metaObj->fields)) {
                $metaObj->fields = array($metaObj->fields);
            }
            $fields = array();
            foreach ($metaObj->fields as $one) {
                $fields[$one->name] = $one;
            }
            $metaObj->fields = $fields;
        }
        return;
    }

    private function generate($def)
    {
        $outputdir = dirname(dirname(__FILE__));

        // create directory
        $base_dir = $outputdir .'/'. 'sobjectdef';
        if (! file_exists($base_dir) && ! mkdir($base_dir)) {
            print "could not make directory for sobjectdef: {$base_dir}";
            exit;
        }
        $sobject_dir = $outputdir .'/'. 'sobject';
        if (! file_exists($sobject_dir) && ! mkdir($sobject_dir)) {
            print "could not make directory for sobject: {$sobject_dir}";
            exit;
        }
        
        // metadata
        $meta = base64_encode(serialize($def));
        $soName = $def->name;
        $soLabel = $def->label;
        
        $classname = "Sobject_{$soName}";
        $baseclassname = "Sobjectdef_{$soName}";
        
        // childrelationship
        $childRelationArrayStr = '';
        $childRelationPropStr = '';
        $childRelationships = array();
        if (isset($def->childRelationships)) {
            foreach ($def->childRelationships as $childdef) {
                if (isset($childdef->relationshipName)) {
                    $childRelationships[$childdef->relationshipName] = $childdef;
                }
            }
            $childRelationArrayStr = $this->getChildRelationArrayStr($childRelationships);
            $childRelationPropStr = $this->getChildRelationPropStr($childRelationships);
        }
        
        // columns
        $parentRelationArrayStr = "";
        $parentRelationships = array();
        $coldef = "";
        $joindef = "";
        foreach($def->fields as $f) {
            
            $coldef .= "    public \$".$f->name."; //".$f->label."\n";
            if ($f->type == 'reference' && isset($f->relationshipName)) {
                $prop = $f->relationshipName;
                $joindef .= <<<EOT
    /**
     * child-parent relationship to {$f->referenceTo}
     * @var Sobject_{$f->referenceTo}
     */
    public \${$prop};


EOT;
                
                $parentRelationships[$prop] = $f->referenceTo;
            }
        }
        if (count($parentRelationships) > 0) {
            $parentRelationArrayStr = $this->getParentRelationArrayStr($parentRelationships);
        }
            
        // create base class 
        $filepath = "{$base_dir}/{$soName}.php";
        if (file_exists($filepath) && ! $this->forceUpdate) {
            print "{$soName}: base class already exists. \n";
            return;
        }
        if (!$handle = fopen($filepath, 'wb')) {
            print "{$filepath}: could not open file. \n";
            return;
        }

        $contents = <<<EOT
/**
 * Entity Base Class for {$soName} ({$soLabel})
 *
 * @package sobjectdef
 */
class {$baseclassname} extends Srecord_ActiveRecord
{
    /**
     * name of sobject
     * @var string
     */
    public static \$_SONAME = '{$soName}';
    
    /**
     * columns
     */
{$coldef}

{$joindef}
{$childRelationPropStr}

{$parentRelationArrayStr}

{$childRelationArrayStr}

    /**
     * serialized metadata (don't delete this.)
     */
    public static \$__meta = '{$meta}';

}
EOT;

        $contents = "<?php \n\n". $contents ."\n\n?>";
        if (fwrite($handle, $contents) === FALSE) {
            print "{$filepath}: failed to write to the file. \n";
            return;
        }

        print "{$soName}: entity base class created . \n";
        fclose($handle);

        // create entity class
        $filepath = "{$sobject_dir}/{$soName}.php";
        if (file_exists($filepath)) {
            print "{$soName}: entity class already exists. \n";
            return;
        }
        if (!$handle = fopen($filepath, 'wb')) {
            print "{$filepath}: could not open file. \n";
            return;
        }

        $contents = <<<EOT
/**
 * Entity Class for {$soName} ({$soLabel})
 * create domain logic here.
 * @package sobject
 */
class {$classname} extends {$baseclassname}
{
    /**
     * get instance of entity
     * @return {$classname}
     */
    public static function get() {
        \$obj = new {$classname}();
        return \$obj;
    }
    
}
EOT;

        $contents = "<?php \n\n". $contents ."\n\n?>";
        if (fwrite($handle, $contents) === FALSE) {
            print "{$filepath}: failed to write to the file. \n";
            return;
        }

        print "{$soName}: entity class created . \n";
        fclose($handle);
    }
    
    private function getChildRelationArrayStr($ar) {
        
        $keyval = "";
        $tmp = array();
        foreach ($ar as $key => $def) {
            $name = $def->childSObject;
            $tmp[] = "'{$key}' => '{$name}'";
        }
        $keyval = implode(",\n        ", $tmp);
        
        $result = <<<EOT
    /**
     * parent-child relationships
     */
    public static \$_childRelationships = array(
        {$keyval}
    );
EOT;

        return $result;
    }
    
    private function getChildRelationPropStr($ar) {
        
        $template = <<<EOT
    /**
     * parent-child relationships to %s
     * @var array 
     */
    public \$%s;


EOT;

        $result = "";
        foreach ($ar as $key => $def) {
            $objName = $def->childSObject;
            $result .= sprintf($template, $objName, $key);
        }
        
        return $result;
    }
    
    private function getParentRelationArrayStr($ar) {
        
        $keyval = "";
        $tmp = array();
        foreach ($ar as $key => $def) {
            $tmp[] = "'{$key}' => '{$def}'";
        }
        $keyval = implode(",\n        ", $tmp);
        
        $result = <<<EOT
    /**
     * child-parent relationships
     */
    public static \$_parentRelationships = array(
        {$keyval}
    );
EOT;

        return $result;
    }
    

}

?>