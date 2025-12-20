// Blog Search Autocomplete
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('blogSearchInput');
    const suggestionsBox = document.getElementById('blogSearchSuggestions');
    const searchForm = document.getElementById('blogSearchForm');
    
    if (!searchInput || !suggestionsBox) return;
    
    let debounceTimer;
    let currentIndex = -1;
    let suggestions = [];
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        currentIndex = -1;
        
        if (query.length < 2) {
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
            return;
        }
        
        debounceTimer = setTimeout(function() {
            fetchSuggestions(query);
        }, 300);
    });
    
    // Keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        if (suggestionsBox.style.display === 'none') return;
        
        const items = suggestionsBox.querySelectorAll('.blog-suggestion-item');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                currentIndex = Math.min(currentIndex + 1, items.length - 1);
                updateSelection(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                currentIndex = Math.max(currentIndex - 1, -1);
                updateSelection(items);
                break;
            case 'Enter':
                e.preventDefault();
                if (currentIndex >= 0 && items[currentIndex]) {
                    items[currentIndex].click();
                } else {
                    searchForm.submit();
                }
                break;
            case 'Escape':
                suggestionsBox.style.display = 'none';
                break;
        }
    });
    
    function fetchSuggestions(query) {
        fetch('/admin/api/suggestions.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                suggestions = data;
                displaySuggestions(data, query);
            })
            .catch(error => console.error('Search error:', error));
    }
    
    function displaySuggestions(data, query) {
        if (data.length === 0) {
            suggestionsBox.innerHTML = '<div style="padding: 12px 16px; color: #999; text-align: center;">No results found</div>';
            suggestionsBox.style.display = 'block';
            return;
        }
        
        suggestionsBox.innerHTML = data.map(item => `
            <a href="/blog/${item.slug}/" class="blog-suggestion-item" style="
                display: block;
                padding: 12px 16px;
                border-bottom: 1px solid #f0f0f0;
                text-decoration: none;
                color: #1a1a1a;
                transition: background-color 0.2s ease;
            "
                onmouseover="this.style.backgroundColor='#f9f9f9'"
                onmouseout="this.style.backgroundColor='transparent'">
                <div style="font-weight: 600; margin-bottom: 4px;">
                    ${highlightMatch(item.title, query)}
                </div>
                <div style="font-size: 13px; color: #666;">
                    ${item.excerpt}
                </div>
            </a>
        `).join('');
        
        suggestionsBox.style.display = 'block';
    }
    
    function highlightMatch(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark style="background-color: #f5d669; padding: 0 2px; border-radius: 2px;">$1</mark>');
    }
    
    function updateSelection(items) {
        items.forEach((item, index) => {
            if (index === currentIndex) {
                item.style.backgroundColor = '#f5d669';
                searchInput.value = suggestions[index]?.title || searchInput.value;
            } else {
                item.style.backgroundColor = 'transparent';
            }
        });
    }
    
    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && e.target !== suggestionsBox) {
            suggestionsBox.style.display = 'none';
        }
    });
    
    // Show suggestions on focus if input has text
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && suggestionsBox.innerHTML) {
            suggestionsBox.style.display = 'block';
        }
    });
});
