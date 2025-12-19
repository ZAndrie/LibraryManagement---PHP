<?php
/**
 * SimpleXLSX - Simple Excel (.xlsx) reader
 * This is a simplified version for basic Excel reading
 * For production, consider using PHPSpreadsheet
 */

class SimpleXLSX {
    private $sheets = [];
    private $sheetNames = [];
    private static $error = '';

    public static function parse($filename) {
        if (!file_exists($filename)) {
            self::$error = "File not found: $filename";
            return false;
        }

        $xlsx = new self();
        
        try {
            $zip = new ZipArchive;
            if ($zip->open($filename) !== true) {
                self::$error = "Cannot open Excel file";
                return false;
            }

            // Read shared strings
            $sharedStrings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $xmlObject = simplexml_load_string($xml);
                if ($xmlObject !== false) {
                    foreach ($xmlObject->si as $si) {
                        $sharedStrings[] = (string)$si->t;
                    }
                }
            }

            // Read worksheet
            $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            if ($worksheet === false) {
                self::$error = "Cannot read worksheet";
                return false;
            }

            $xmlObject = simplexml_load_string($worksheet);
            if ($xmlObject === false) {
                self::$error = "Cannot parse worksheet XML";
                return false;
            }

            $rows = [];
            foreach ($xmlObject->sheetData->row as $row) {
                $rowData = [];
                foreach ($row->c as $cell) {
                    $value = '';
                    
                    // Check cell type
                    $type = (string)$cell['t'];
                    
                    if ($type == 's') {
                        // Shared string
                        $index = (int)$cell->v;
                        $value = isset($sharedStrings[$index]) ? $sharedStrings[$index] : '';
                    } else {
                        // Direct value
                        $value = (string)$cell->v;
                    }
                    
                    $rowData[] = $value;
                }
                $rows[] = $rowData;
            }

            $xlsx->sheets[0] = $rows;
            return $xlsx;

        } catch (Exception $e) {
            self::$error = "Error: " . $e->getMessage();
            return false;
        }
    }

    public function rows($sheetIndex = 0) {
        return isset($this->sheets[$sheetIndex]) ? $this->sheets[$sheetIndex] : [];
    }

    public static function parseError() {
        return self::$error;
    }
}
?>