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

### Developing

Too many bugs, fixing now

We might learn some methods from https://github.com/evanplaice/jquery-csv and develop, not sure yet

## License

This project is licensed under the MIT License -
