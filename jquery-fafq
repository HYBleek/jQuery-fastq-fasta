/**
 * Fast FASTA/FASTQ Sequence Search Tool for Node.js
 * Optimized for web server usage with streaming and async operations
 */

const fs = require('fs');
const readline = require('readline');
const { Transform } = require('stream');
const path = require('path');

class SequenceSearcher {
    constructor(filename) {
        this.filename = filename;
        this.fileType = this.detectFileType();
    }

    detectFileType() {
        const ext = path.extname(this.filename).toLowerCase();
        if (['.fa', '.fasta'].includes(ext)) {
            return 'fasta';
        } else if (['.fq', '.fastq'].includes(ext)) {
            return 'fastq';
        }
        
        // Auto-detect from content if extension is unclear
        try {
            const firstLine = fs.readFileSync(this.filename, 'utf8').split('\n')[0];
            if (firstLine.startsWith('>')) return 'fasta';
            if (firstLine.startsWith('@')) return 'fastq';
        } catch (err) {
            throw new Error(`Cannot determine file type: ${err.message}`);
        }
        
        throw new Error('Unknown file format');
    }

    /**
     * Stream-based search - memory efficient for large files
     * Returns a promise that resolves to array of results
     */
    async searchSequenceStream(partialName) {
        return new Promise((resolve, reject) => {
            const results = [];
            const fileStream = fs.createReadStream(this.filename);
            const rl = readline.createInterface({
                input: fileStream,
                crlfDelay: Infinity
            });

            let lineNumber = 0;
            let currentHeader = null;
            let currentLineNum = 0;
            let currentSequence = null;
            let expectingQuality = false;

            rl.on('line', (line) => {
                lineNumber++;
                line = line.trim();

                if (!line) return;

                const headerChar = this.fileType === 'fasta' ? '>' : '@';

                if (line.startsWith(headerChar)) {
                    // Process previous sequence if it matched
                    if (currentHeader && currentHeader.includes(partialName)) {
                        results.push({
                            lineNumber: currentLineNum,
                            header: currentHeader,
                            sequence: currentSequence,
                            quality: null
                        });
                    }

                    // Start new sequence
                    currentHeader = line.substring(1);
                    currentLineNum = lineNumber;
                    currentSequence = null;
                    expectingQuality = false;
                } else if (currentHeader && !currentSequence) {
                    // This is the sequence line
                    currentSequence = line;
                    if (this.fileType === 'fastq') {
                        expectingQuality = true;
                    }
                } else if (this.fileType === 'fastq' && line.startsWith('+')) {
                    // Plus line in FASTQ, next line is quality
                } else if (this.fileType === 'fastq' && expectingQuality && currentSequence) {
                    // Quality line in FASTQ
                    if (currentHeader.includes(partialName)) {
                        results.push({
                            lineNumber: currentLineNum,
                            header: currentHeader,
                            sequence: currentSequence,
                            quality: line
                        });
                    }
                    currentHeader = null;
                    expectingQuality = false;
                }
            });

            rl.on('close', () => {
                // Handle last sequence for FASTA files
                if (this.fileType === 'fasta' && currentHeader && 
                    currentHeader.includes(partialName) && currentSequence) {
                    results.push({
                        lineNumber: currentLineNum,
                        header: currentHeader,
                        sequence: currentSequence,
                        quality: null
                    });
                }
                resolve(results);
            });

            rl.on('error', reject);
        });
    }

    /**
     * Buffer-based search - faster for smaller files
     * Loads entire file into memory
     */
    async searchSequenceFast(partialName) {
        const content = await fs.promises.readFile(this.filename, 'utf8');
        const lines = content.split('\n');
        const results = [];
        
        let i = 0;
        while (i < lines.length) {
            const line = lines[i].trim();
            
            if (!line) {
                i++;
                continue;
            }
            
            const headerChar = this.fileType === 'fasta' ? '>' : '@';
            
            if (line.startsWith(headerChar)) {
                const header = line.substring(1);
                
                if (header.includes(partialName)) {
                    const lineNumber = i + 1;
                    
                    // Get sequence
                    if (i + 1 < lines.length) {
                        const sequence = lines[i + 1].trim();
                        
                        let quality = null;
                        if (this.fileType === 'fastq' && i + 3 < lines.length) {
                            quality = lines[i + 3].trim();
                        }
                        
                        results.push({
                            lineNumber,
                            header,
                            sequence,
                            quality
                        });
                    }
                }
                
                // Skip to next record
                i += this.fileType === 'fasta' ? 2 : 4;
            } else {
                i++;
            }
        }
        
        return results;
    }

    /**
     * Regex-based search - good balance of speed and memory
     */
    async searchSequenceRegex(partialName) {
        const content = await fs.promises.readFile(this.filename, 'utf8');
        const results = [];
        
        // Escape special regex characters in the search term
        const escapedName = partialName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        
        // Create regex pattern based on file type
        const headerChar = this.fileType === 'fasta' ? '>' : '@';
        const pattern = new RegExp(
            `^\\${headerChar}(.*${escapedName}.*)\\n([^\\n]+)(?:\\n\\+\\n([^\\n]+))?`,
            'gm'
        );
        
        let match;
        let lineOffset = 0;
        
        while ((match = pattern.exec(content)) !== null) {
            // Calculate line number
            const beforeMatch = content.substring(0, match.index);
            const lineNumber = beforeMatch.split('\n').length;
            
            results.push({
                lineNumber,
                header: match[1],
                sequence: match[2],
                quality: match[3] || null
            });
        }
        
        return results;
    }
}

/**
 * Express.js route handler example
 */
async function searchSequenceHandler(req, res) {
    try {
        const { filename, partialName, method = 'stream' } = req.query;
        
        if (!filename || !partialName) {
            return res.status(400).json({
                error: 'Missing required parameters: filename and partialName'
            });
        }
        
        const searcher = new SequenceSearcher(filename);
        
        let results;
        switch (method) {
            case 'fast':
                results = await searcher.searchSequenceFast(partialName);
                break;
            case 'regex':
                results = await searcher.searchSequenceRegex(partialName);
                break;
            case 'stream':
            default:
                results = await searcher.searchSequenceStream(partialName);
                break;
        }
        
        res.json({
            success: true,
            count: results.length,
            results: results
        });
        
    } catch (error) {
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
}

/**
 * Command line interface
 */
if (require.main === module) {
    const args = process.argv.slice(2);
    
    if (args.length !== 2) {
        console.log('Usage: node seq_search.js <filename> <partial_sequence_name>');
        console.log('Example: node seq_search.js data.fasta seq123');
        process.exit(1);
    }
    
    const [filename, partialName] = args;
    
    const searcher = new SequenceSearcher(filename);
    
    // Use stream method for command line (memory efficient)
    searcher.searchSequenceStream(partialName)
        .then(results => {
            if (results.length === 0) {
                console.log(`No sequences found containing '${partialName}'`);
            } else {
                console.log(`Found ${results.length} sequence(s) containing '${partialName}':\n`);
                
                results.forEach(result => {
                    console.log(`Line number: ${result.lineNumber}`);
                    console.log(`Sequence name: ${result.header}`);
                    console.log(`Sequence data: ${result.sequence}`);
                    if (result.quality) {
                        console.log(`Quality scores: ${result.quality}`);
                    }
                    console.log('-'.repeat(50));
                });
            }
        })
        .catch(error => {
            console.error(`Error: ${error.message}`);
            process.exit(1);
        });
}

// Export for use as module
module.exports = {
    SequenceSearcher,
    searchSequenceHandler
};
