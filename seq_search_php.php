<?php
/**
 * Fast FASTA/FASTQ Sequence Search Tool for PHP
 * Optimized for web server usage with memory-efficient operations
 */

class SequenceSearcher {
    private $filename;
    private $fileType;
    
    public function __construct($filename) {
        $this->filename = $filename;
        $this->fileType = $this->detectFileType();
    }
    
    private function detectFileType() {
        $ext = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, ['fa', 'fasta'])) {
            return 'fasta';
        } elseif (in_array($ext, ['fq', 'fastq'])) {
            return 'fastq';
        }
        
        // Auto-detect from content
        if (!file_exists($this->filename)) {
            throw new Exception("File not found: {$this->filename}");
        }
        
        $handle = fopen($this->filename, 'r');
        if ($handle) {
            $firstLine = fgets($handle);
            fclose($handle);
            
            if ($firstLine[0] === '>') return 'fasta';
            if ($firstLine[0] === '@') return 'fastq';
        }
        
        throw new Exception("Cannot determine file type");
    }
    
    /**
     * Stream-based search - memory efficient for large files
     * Reads file line by line without loading entire content
     */
    public function searchSequenceStream($partialName) {
        $results = [];
        
        if (!file_exists($this->filename)) {
            throw new Exception("File not found: {$this->filename}");
        }
        
        $handle = fopen($this->filename, 'r');
        if (!$handle) {
            throw new Exception("Cannot open file: {$this->filename}");
        }
        
        $lineNumber = 0;
        $currentHeader = null;
        $currentLineNum = 0;
        $currentSequence = null;
        $expectingQuality = false;
        $inQualitySection = false;
        
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);
            
            if (empty($line)) continue;
            
            $headerChar = $this->fileType === 'fasta' ? '>' : '@';
            
            if ($line[0] === $headerChar) {
                // Process previous sequence if it matched
                if ($currentHeader !== null && strpos($currentHeader, $partialName) !== false) {
                    $results[] = [
                        'lineNumber' => $currentLineNum,
                        'header' => $currentHeader,
                        'sequence' => $currentSequence,
                        'quality' => null
                    ];
                }
                
                // Start new sequence
                $currentHeader = substr($line, 1);
                $currentLineNum = $lineNumber;
                $currentSequence = null;
                $expectingQuality = false;
                $inQualitySection = false;
            } elseif ($currentHeader !== null && $currentSequence === null) {
                // This is the sequence line
                $currentSequence = $line;
                if ($this->fileType === 'fastq') {
                    $expectingQuality = true;
                }
            } elseif ($this->fileType === 'fastq' && $line[0] === '+') {
                // Plus line in FASTQ
                $inQualitySection = true;
            } elseif ($this->fileType === 'fastq' && $inQualitySection && $currentSequence !== null) {
                // Quality line in FASTQ
                if (strpos($currentHeader, $partialName) !== false) {
                    $results[] = [
                        'lineNumber' => $currentLineNum,
                        'header' => $currentHeader,
                        'sequence' => $currentSequence,
                        'quality' => $line
                    ];
                }
                $currentHeader = null;
                $inQualitySection = false;
            }
        }
        
        // Handle last sequence for FASTA files
        if ($this->fileType === 'fasta' && $currentHeader !== null && 
            strpos($currentHeader, $partialName) !== false && $currentSequence !== null) {
            $results[] = [
                'lineNumber' => $currentLineNum,
                'header' => $currentHeader,
                'sequence' => $currentSequence,
                'quality' => null
            ];
        }
        
        fclose($handle);
        return $results;
    }
    
    /**
     * Memory-mapped search - faster for files that fit in memory
     * Uses file_get_contents for simplicity
     */
    public function searchSequenceFast($partialName) {
        $content = file_get_contents($this->filename);
        if ($content === false) {
            throw new Exception("Cannot read file: {$this->filename}");
        }
        
        $lines = explode("\n", $content);
        $results = [];
        
        $i = 0;
        $lineCount = count($lines);
        
        while ($i < $lineCount) {
            $line = trim($lines[$i]);
            
            if (empty($line)) {
                $i++;
                continue;
            }
            
            $headerChar = $this->fileType === 'fasta' ? '>' : '@';
            
            if ($line[0] === $headerChar) {
                $header = substr($line, 1);
                
                if (strpos($header, $partialName) !== false) {
                    $lineNumber = $i + 1;
                    
                    // Get sequence
                    if ($i + 1 < $lineCount) {
                        $sequence = trim($lines[$i + 1]);
                        
                        $quality = null;
                        if ($this->fileType === 'fastq' && $i + 3 < $lineCount) {
                            $quality = trim($lines[$i + 3]);
                        }
                        
                        $results[] = [
                            'lineNumber' => $lineNumber,
                            'header' => $header,
                            'sequence' => $sequence,
                            'quality' => $quality
                        ];
                    }
                }
                
                // Skip to next record
                $i += $this->fileType === 'fasta' ? 2 : 4;
            } else {
                $i++;
            }
        }
        
        return $results;
    }
    
    /**
     * Regex-based search - good balance for medium files
     */
    public function searchSequenceRegex($partialName) {
        $content = file_get_contents($this->filename);
        if ($content === false) {
            throw new Exception("Cannot read file: {$this->filename}");
        }
        
        $results = [];
        
        // Escape special regex characters
        $escapedName = preg_quote($partialName, '/');
        
        // Create pattern based on file type
        $headerChar = $this->fileType === 'fasta' ? '>' : '@';
        
        if ($this->fileType === 'fasta') {
            $pattern = '/^\\' . $headerChar . '(.*' . $escapedName . '.*)\\n([^\\n]+)/m';
        } else {
            // FASTQ pattern includes quality
            $pattern = '/^\\' . $headerChar . '(.*' . $escapedName . '.*)\\n([^\\n]+)\\n\\+\\n([^\\n]+)/m';
        }
        
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $idx => $match) {
                // Calculate line number
                $beforeMatch = substr($content, 0, $match[1]);
                $lineNumber = substr_count($beforeMatch, "\n") + 1;
                
                $results[] = [
                    'lineNumber' => $lineNumber,
                    'header' => $matches[1][$idx][0],
                    'sequence' => $matches[2][$idx][0],
                    'quality' => isset($matches[3][$idx]) ? $matches[3][$idx][0] : null
                ];
            }
        }
        
        return $results;
    }
}

/**
 * Web API endpoint handler
 */
function handleSearchRequest() {
    header('Content-Type: application/json');
    
    try {
        // Get parameters from either GET or POST
        $filename = $_REQUEST['filename'] ?? null;
        $partialName = $_REQUEST['partialName'] ?? null;
        $method = $_REQUEST['method'] ?? 'stream';
        
        if (!$filename || !$partialName) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing required parameters: filename and partialName'
            ]);
            return;
        }
        
        // Security: Validate filename to prevent directory traversal
        $filename = basename($filename);
        $allowedDir = '/path/to/sequence/files/'; // Configure this
        $fullPath = $allowedDir . $filename;
        
        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'File not found'
            ]);
            return;
        }
        
        $searcher = new SequenceSearcher($fullPath);
        
        switch ($method) {
            case 'fast':
                $results = $searcher->searchSequenceFast($partialName);
                break;
            case 'regex':
                $results = $searcher->searchSequenceRegex($partialName);
                break;
            case 'stream':
            default:
                $results = $searcher->searchSequenceStream($partialName);
                break;
        }
        
        echo json_encode([
            'success' => true,
            'count' => count($results),
            'results' => $results
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Command line interface
 */
if (php_sapi_name() === 'cli') {
    if ($argc !== 3) {
        echo "Usage: php seq_search.php <filename> <partial_sequence_name>\n";
        echo "Example: php seq_search.php data.fasta seq123\n";
        exit(1);
    }
    
    $filename = $argv[1];
    $partialName = $argv[2];
    
    try {
        $searcher = new SequenceSearcher($filename);
        
        // Use stream method for CLI (memory efficient)
        $results = $searcher->searchSequenceStream($partialName);
        
        if (empty($results)) {
            echo "No sequences found containing '$partialName'\n";
        } else {
            echo "Found " . count($results) . " sequence(s) containing '$partialName':\n\n";
            
            foreach ($results as $result) {
                echo "Line number: {$result['lineNumber']}\n";
                echo "Sequence name: {$result['header']}\n";
                echo "Sequence data: {$result['sequence']}\n";
                if ($result['quality']) {
                    echo "Quality scores: {$result['quality']}\n";
                }
                echo str_repeat('-', 50) . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Web request
    handleSearchRequest();
}
