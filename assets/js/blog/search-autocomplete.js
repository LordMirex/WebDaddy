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
            suggestionsBox.classList.remove('active');
            suggestionsBox.innerHTML = '';
            return;
        }
        
        debounceTimer = setTimeout(function() {
            fetchSuggestions(query);
        }, 300);
    });
    
    // Keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        if (!suggestionsBox.classList.contains('active')) return;
        
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
                suggestionsBox.classList.remove('active');
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
            suggestionsBox.innerHTML = '<div style="padding: 12px 16px; color: #666; text-align: center; font-size: 13px;">No results found</div>';
            suggestionsBox.classList.add('active');
            return;
        }
        
        suggestionsBox.innerHTML = data.map(item => `
            <a href="/blog/${item.slug}/" class="blog-suggestion-item">
                <div class="blog-suggestion-item-title">
                    ${highlightMatch(item.title, query)}
                </div>
                <div class="blog-suggestion-item-excerpt">
                    ${item.excerpt || 'No preview available'}
                </div>
            </a>
        `).join('');
        
        suggestionsBox.classList.add('active');
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
        if (e.target !== searchInput && !suggestionsBox.contains(e.target)) {
            suggestionsBox.classList.remove('active');
        }
    });
    
    // Show suggestions on focus if input has text
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && suggestionsBox.innerHTML && suggestionsBox.classList.contains('active')) {
            suggestionsBox.classList.add('active');
        }
    });
});
