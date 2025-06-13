# fastq-fasta--websearch


A high-performance tool for searching DNA sequences in FASTA and FASTQ files by partial sequence name matching. Available in both Python and C++ implementations.

## Features

- **Fast searching**: Uses memory-mapped I/O (C++) and optimized regex patterns (Python) for rapid sequence identification
- **Support for both FASTA and FASTQ formats**: Automatically detects file type based on extension or content
- **Partial name matching**: Find sequences containing any part of the specified name
- **Line number reporting**: Returns the exact line number where each matching sequence starts
- **Full sequence extraction**: Retrieves complete sequence data and quality scores (for FASTQ)

## File Format Support

### FASTA Format (.fa, .fasta)
```
>sequence_name_1
ATCGATCGATCGATCGATCG
>sequence_name_2
GCTAGCTAGCTAGCTAGCTA
```

### FASTQ Format (.fq, .fastq)
```
@sequence_name_1
ATCGATCGATCGATCGATCG
+
IIIIIIIIIIIIIIIIIIII
@sequence_name_2
GCTAGCTAGCTAGCTAGCTA
+
HHHHHHHHHHHHHHHHHHHH
```

## Installation

### Python Version

Requirements:
- Python 3.6+
- No external dependencies (uses standard library only)

```bash
# Make executable
chmod +x seq_search.py
```

### C++ Version

Requirements:
- C++11 compatible compiler
- POSIX system for memory-mapped I/O support (Linux, macOS, Unix)

```bash
# Compile with optimization
g++ -O3 -std=c++11 -o seq_search seq_search.cpp

# Or with debugging symbols
g++ -g -std=c++11 -o seq_search seq_search.cpp
```

## Usage

Both versions have identical command-line interfaces:

```bash
# Python version
python seq_search.py <filename> <partial_sequence_name>

# C++ version
./seq_search <filename> <partial_sequence_name>
```

### Examples

Search for sequences containing "chr1" in a FASTA file:
```bash
python seq_search.py genome.fasta chr1
```

Search for sequences containing "read_123" in a FASTQ file:
```bash
./seq_search reads.fastq read_123
```

## Output Format

The tool outputs all matching sequences with the following information:

```
Found 2 sequence(s) containing 'chr1':

Line number: 5
Sequence name: chr1_region_001
Sequence data: ATCGATCGATCGATCGATCGATCG
--------------------------------------------------
Line number: 125
Sequence name: chr1_region_047
Sequence data: GCTAGCTAGCTAGCTAGCTAGCTA
--------------------------------------------------
```

For FASTQ files, quality scores are also displayed:
```
Line number: 5
Sequence name: read_chr1_001
Sequence data: ATCGATCGATCGATCGATCGATCG
Quality scores: IIIIIIIIIIIIIIIIIIIIIIII
--------------------------------------------------
```

## Performance Optimization

### Python Implementation
- Uses `mmap` for memory-efficient file reading
- Employs compiled regex patterns for fast header matching
- Reads entire file into memory for optimal search speed

### C++ Implementation
- Uses memory-mapped I/O (`mmap`) on Unix-like systems for zero-copy file access
- Falls back to standard file I/O on other systems
- Minimal memory allocation during search
- Optimized string searching without regex overhead

## Performance Tips

1. **For very large files** (>1GB): The C++ version with memory-mapped I/O will typically be faster
2. **For many searches**: Consider indexing your sequences using specialized tools like `samtools faidx`
3. **File location**: Store files on fast storage (SSD) for best performance
4. **Memory**: Ensure sufficient RAM for the file size when using memory-mapped approaches

## Limitations

- The search is case-sensitive
- Partial name matching uses simple substring search
- For extremely large files (>10GB), consider using indexed formats

## Error Handling

Both implementations handle common errors:
- File not found
- Invalid file format
- Corrupted file structure
- Insufficient permissions

## Building from Source

### Running Tests (Python)
```bash
# Create test files
echo -e ">test_seq_1\nATCGATCG\n>test_seq_2\nGCTAGCTA" > test.fasta
echo -e "@test_read_1\nATCGATCG\n+\nIIIIIIII\n@test_read_2\nGCTAGCTA\n+\nHHHHHHHH" > test.fastq

# Test Python version
python seq_search.py test.fasta test_seq
python seq_search.py test.fastq test_read

# Test C++ version
./seq_search test.fasta test_seq
./seq_search test.fastq test_read
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

### Development Guidelines

1. Maintain backward compatibility
2. Add tests for new features
3. Update documentation
4. Follow existing code style
5. Optimize for performance

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- Inspired by the need for fast sequence searching in bioinformatics pipelines
- Optimized for modern multi-core systems
- Designed with large-scale genomic data in mind

## Future Improvements

- [ ] Add support for compressed files (gzip, bzip2)
- [ ] Implement parallel search for multi-core systems
- [ ] Add regular expression support for complex pattern matching
- [ ] Create Python bindings for C++ version
- [ ] Add progress bar for large file searches
- [ ] Support for amino acid sequences
- [ ] Case-insensitive search option