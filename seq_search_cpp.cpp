#include <iostream>
#include <fstream>
#include <string>
#include <vector>
#include <algorithm>
#include <cstring>
#include <memory>
#include <sstream>
#include <sys/mman.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

struct SequenceResult {
    size_t line_number;
    std::string header;
    std::string sequence;
    std::string quality;  // Empty for FASTA
};

class SequenceSearcher {
private:
    std::string filename;
    std::string file_type;
    
    std::string detect_file_type() {
        // Check extension
        size_t dot_pos = filename.find_last_of('.');
        if (dot_pos != std::string::npos) {
            std::string ext = filename.substr(dot_pos);
            std::transform(ext.begin(), ext.end(), ext.begin(), ::tolower);
            
            if (ext == ".fa" || ext == ".fasta") {
                return "fasta";
            } else if (ext == ".fq" || ext == ".fastq") {
                return "fastq";
            }
        }
        
        // Check content
        std::ifstream file(filename);
        if (!file.is_open()) {
            throw std::runtime_error("Cannot open file: " + filename);
        }
        
        std::string first_line;
        std::getline(file, first_line);
        file.close();
        
        if (!first_line.empty()) {
            if (first_line[0] == '>') {
                return "fasta";
            } else if (first_line[0] == '@') {
                return "fastq";
            }
        }
        
        throw std::runtime_error("Cannot determine file type");
    }
    
public:
    SequenceSearcher(const std::string& fname) : filename(fname) {
        file_type = detect_file_type();
    }
    
    std::vector<SequenceResult> search_sequence_mmap(const std::string& partial_name) {
        std::vector<SequenceResult> results;
        
        // Open file
        int fd = open(filename.c_str(), O_RDONLY);
        if (fd == -1) {
            throw std::runtime_error("Cannot open file: " + filename);
        }
        
        // Get file size
        struct stat sb;
        if (fstat(fd, &sb) == -1) {
            close(fd);
            throw std::runtime_error("Cannot get file size");
        }
        
        // Memory map the file
        char* file_content = static_cast<char*>(
            mmap(nullptr, sb.st_size, PROT_READ, MAP_PRIVATE, fd, 0)
        );
        
        if (file_content == MAP_FAILED) {
            close(fd);
            throw std::runtime_error("Memory mapping failed");
        }
        
        // Search through the file
        size_t line_number = 1;
        const char* current = file_content;
        const char* end = file_content + sb.st_size;
        
        while (current < end) {
            // Skip empty lines
            while (current < end && (*current == '\n' || *current == '\r')) {
                if (*current == '\n') line_number++;
                current++;
            }
            
            if (current >= end) break;
            
            // Check for sequence header
            char header_char = (file_type == "fasta") ? '>' : '@';
            
            if (*current == header_char) {
                // Found a header, extract it
                const char* header_start = current + 1;  // Skip > or @
                const char* header_end = header_start;
                
                // Find end of header line
                while (header_end < end && *header_end != '\n' && *header_end != '\r') {
                    header_end++;
                }
                
                std::string header(header_start, header_end - header_start);
                
                // Check if partial name matches
                if (header.find(partial_name) != std::string::npos) {
                    SequenceResult result;
                    result.line_number = line_number;
                    result.header = header;
                    
                    // Move to sequence line
                    current = header_end;
                    while (current < end && (*current == '\n' || *current == '\r')) {
                        if (*current == '\n') line_number++;
                        current++;
                    }
                    
                    // Extract sequence
                    const char* seq_start = current;
                    const char* seq_end = seq_start;
                    while (seq_end < end && *seq_end != '\n' && *seq_end != '\r') {
                        seq_end++;
                    }
                    result.sequence = std::string(seq_start, seq_end - seq_start);
                    
                    // For FASTQ, also get quality
                    if (file_type == "fastq") {
                        // Skip to + line
                        current = seq_end;
                        while (current < end && (*current == '\n' || *current == '\r')) {
                            if (*current == '\n') line_number++;
                            current++;
                        }
                        
                        // Skip + line
                        while (current < end && *current != '\n' && *current != '\r') {
                            current++;
                        }
                        while (current < end && (*current == '\n' || *current == '\r')) {
                            if (*current == '\n') line_number++;
                            current++;
                        }
                        
                        // Extract quality
                        const char* qual_start = current;
                        const char* qual_end = qual_start;
                        while (qual_end < end && *qual_end != '\n' && *qual_end != '\r') {
                            qual_end++;
                        }
                        result.quality = std::string(qual_start, qual_end - qual_start);
                        
                        current = qual_end;
                    } else {
                        current = seq_end;
                    }
                    
                    results.push_back(result);
                } else {
                    // Skip this record
                    current = header_end;
                    
                    // Skip lines based on file type
                    int lines_to_skip = (file_type == "fasta") ? 1 : 3;
                    for (int i = 0; i < lines_to_skip && current < end; i++) {
                        while (current < end && *current != '\n') {
                            current++;
                        }
                        if (current < end && *current == '\n') {
                            line_number++;
                            current++;
                        }
                    }
                }
            } else {
                // Skip to next line
                while (current < end && *current != '\n') {
                    current++;
                }
                if (current < end && *current == '\n') {
                    line_number++;
                    current++;
                }
            }
        }
        
        // Cleanup
        munmap(file_content, sb.st_size);
        close(fd);
        
        return results;
    }
    
    // Alternative method using standard file I/O for portability
    std::vector<SequenceResult> search_sequence(const std::string& partial_name) {
        std::vector<SequenceResult> results;
        std::ifstream file(filename);
        
        if (!file.is_open()) {
            throw std::runtime_error("Cannot open file: " + filename);
        }
        
        std::string line;
        size_t line_number = 0;
        
        while (std::getline(file, line)) {
            line_number++;
            
            // Trim whitespace
            line.erase(0, line.find_first_not_of(" \t\r\n"));
            line.erase(line.find_last_not_of(" \t\r\n") + 1);
            
            if (line.empty()) continue;
            
            char header_char = (file_type == "fasta") ? '>' : '@';
            
            if (line[0] == header_char) {
                std::string header = line.substr(1);
                
                if (header.find(partial_name) != std::string::npos) {
                    SequenceResult result;
                    result.line_number = line_number;
                    result.header = header;
                    
                    // Get sequence
                    if (std::getline(file, line)) {
                        line_number++;
                        line.erase(0, line.find_first_not_of(" \t\r\n"));
                        line.erase(line.find_last_not_of(" \t\r\n") + 1);
                        result.sequence = line;
                        
                        if (file_type == "fastq") {
                            // Skip + line
                            if (std::getline(file, line)) {
                                line_number++;
                                
                                // Get quality
                                if (std::getline(file, line)) {
                                    line_number++;
                                    line.erase(0, line.find_first_not_of(" \t\r\n"));
                                    line.erase(line.find_last_not_of(" \t\r\n") + 1);
                                    result.quality = line;
                                }
                            }
                        }
                    }
                    
                    results.push_back(result);
                } else {
                    // Skip this record
                    int lines_to_skip = (file_type == "fasta") ? 1 : 3;
                    for (int i = 0; i < lines_to_skip; i++) {
                        if (!std::getline(file, line)) break;
                        line_number++;
                    }
                }
            }
        }
        
        file.close();
        return results;
    }
};

int main(int argc, char* argv[]) {
    if (argc != 3) {
        std::cerr << "Usage: " << argv[0] << " <filename> <partial_sequence_name>" << std::endl;
        std::cerr << "Example: " << argv[0] << " data.fasta seq123" << std::endl;
        return 1;
    }
    
    std::string filename = argv[1];
    std::string partial_name = argv[2];
    
    try {
        SequenceSearcher searcher(filename);
        
        // Use memory-mapped I/O for better performance on Unix-like systems
        #ifdef __unix__
            auto results = searcher.search_sequence_mmap(partial_name);
        #else
            auto results = searcher.search_sequence(partial_name);
        #endif
        
        if (results.empty()) {
            std::cout << "No sequences found containing '" << partial_name << "'" << std::endl;
        } else {
            std::cout << "Found " << results.size() << " sequence(s) containing '" 
                     << partial_name << "':\n" << std::endl;
            
            for (const auto& result : results) {
                std::cout << "Line number: " << result.line_number << std::endl;
                std::cout << "Sequence name: " << result.header << std::endl;
                std::cout << "Sequence data: " << result.sequence << std::endl;
                if (!result.quality.empty()) {
                    std::cout << "Quality scores: " << result.quality << std::endl;
                }
                std::cout << std::string(50, '-') << std::endl;
            }
        }
        
    } catch (const std::exception& e) {
        std::cerr << "Error: " << e.what() << std::endl;
        return 1;
    }
    
    return 0;
}
