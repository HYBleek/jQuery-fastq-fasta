#!/usr/bin/env python3
"""
Fast FASTA/FASTQ sequence search tool
Searches for sequences by partial name match and returns line numbers and sequence data
"""

import sys
import os
from typing import Tuple, Optional, List
import mmap
import re


class SequenceSearcher:
    def __init__(self, filename: str):
        """Initialize the sequence searcher with a file."""
        self.filename = filename
        self.file_type = self._detect_file_type()
        
    def _detect_file_type(self) -> str:
        """Detect if file is FASTA or FASTQ based on extension."""
        ext = os.path.splitext(self.filename)[1].lower()
        if ext in ['.fa', '.fasta']:
            return 'fasta'
        elif ext in ['.fq', '.fastq']:
            return 'fastq'
        else:
            # Try to detect by content
            with open(self.filename, 'r') as f:
                first_line = f.readline().strip()
                if first_line.startswith('>'):
                    return 'fasta'
                elif first_line.startswith('@'):
                    return 'fastq'
            raise ValueError(f"Cannot determine file type for {self.filename}")
    
    def search_sequence(self, partial_name: str) -> List[Tuple[int, str, str, Optional[str]]]:
        """
        Search for sequences containing the partial name.
        Returns list of tuples: (line_number, full_name, sequence, quality)
        Quality is None for FASTA files.
        """
        results = []
        
        with open(self.filename, 'rb') as f:
            # Use mmap for faster file reading
            with mmap.mmap(f.fileno(), 0, access=mmap.ACCESS_READ) as mmapped_file:
                # Convert to string for easier processing
                content = mmapped_file.read().decode('utf-8')
                lines = content.split('\n')
                
                i = 0
                while i < len(lines):
                    line = lines[i].strip()
                    
                    if not line:
                        i += 1
                        continue
                    
                    # Check for sequence header
                    if (self.file_type == 'fasta' and line.startswith('>')) or \
                       (self.file_type == 'fastq' and line.startswith('@')):
                        
                        header = line[1:]  # Remove > or @ symbol
                        
                        # Check if partial name matches
                        if partial_name in header:
                            line_number = i + 1  # 1-based line numbering
                            
                            # Get sequence data
                            if i + 1 < len(lines):
                                sequence = lines[i + 1].strip()
                                
                                quality = None
                                if self.file_type == 'fastq' and i + 3 < len(lines):
                                    # FASTQ has + line and quality line
                                    quality = lines[i + 3].strip()
                                    
                                results.append((line_number, header, sequence, quality))
                            
                            # Skip processed lines
                            if self.file_type == 'fasta':
                                i += 2
                            else:  # fastq
                                i += 4
                        else:
                            # Skip to next record
                            if self.file_type == 'fasta':
                                i += 2
                            else:  # fastq
                                i += 4
                    else:
                        i += 1
        
        return results
    
    def search_sequence_fast(self, partial_name: str) -> List[Tuple[int, str, str, Optional[str]]]:
        """
        Optimized search using regex for finding headers quickly.
        """
        results = []
        
        with open(self.filename, 'rb') as f:
            with mmap.mmap(f.fileno(), 0, access=mmap.ACCESS_READ) as mmapped_file:
                content = mmapped_file.read().decode('utf-8')
                
                # Create regex pattern for headers
                if self.file_type == 'fasta':
                    header_pattern = re.compile(r'^>.*' + re.escape(partial_name) + r'.*$', re.MULTILINE)
                else:  # fastq
                    header_pattern = re.compile(r'^@.*' + re.escape(partial_name) + r'.*$', re.MULTILINE)
                
                # Find all matching headers
                for match in header_pattern.finditer(content):
                    start_pos = match.start()
                    
                    # Calculate line number
                    line_number = content[:start_pos].count('\n') + 1
                    
                    # Extract header
                    header = match.group()[1:]  # Remove > or @
                    
                    # Find end of line
                    line_end = content.find('\n', match.end())
                    if line_end == -1:
                        continue
                    
                    # Get sequence
                    seq_start = line_end + 1
                    seq_end = content.find('\n', seq_start)
                    if seq_end == -1:
                        sequence = content[seq_start:].strip()
                    else:
                        sequence = content[seq_start:seq_end].strip()
                    
                    quality = None
                    if self.file_type == 'fastq':
                        # Skip + line
                        plus_end = content.find('\n', seq_end + 1)
                        if plus_end != -1:
                            qual_start = plus_end + 1
                            qual_end = content.find('\n', qual_start)
                            if qual_end == -1:
                                quality = content[qual_start:].strip()
                            else:
                                quality = content[qual_start:qual_end].strip()
                    
                    results.append((line_number, header, sequence, quality))
        
        return results


def main():
    """Main function to handle command line arguments."""
    if len(sys.argv) != 3:
        print("Usage: python seq_search.py <filename> <partial_sequence_name>")
        print("Example: python seq_search.py data.fasta seq123")
        sys.exit(1)
    
    filename = sys.argv[1]
    partial_name = sys.argv[2]
    
    if not os.path.exists(filename):
        print(f"Error: File '{filename}' not found")
        sys.exit(1)
    
    try:
        searcher = SequenceSearcher(filename)
        
        # Use the fast search method
        results = searcher.search_sequence_fast(partial_name)
        
        if not results:
            print(f"No sequences found containing '{partial_name}'")
        else:
            print(f"Found {len(results)} sequence(s) containing '{partial_name}':\n")
            
            for line_num, header, sequence, quality in results:
                print(f"Line number: {line_num}")
                print(f"Sequence name: {header}")
                print(f"Sequence data: {sequence}")
                if quality:
                    print(f"Quality scores: {quality}")
                print("-" * 50)
                
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
