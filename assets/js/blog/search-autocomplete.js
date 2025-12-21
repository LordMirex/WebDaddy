// Blog Search Autocomplete - Enhanced
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('blogSearchInput');
    const suggestionsBox = document.getElementById('blogSearchSuggestions');
    const searchForm = document.getElementById('blogSearchForm');
    
    if (!searchInput || !suggestionsBox) return;
    
    let debounceTimer;
    let currentIndex = -1;
    let suggestions = [];
    
    // Handle input and show clear button
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        currentIndex = -1;
        
        // Toggle clear button visibility
        updateClearButton();
        
        if (query.length < 1) {
            suggestionsBox.classList.remove('active');
            suggestionsBox.innerHTML = '';
            return;
        }
        
        debounceTimer = setTimeout(function() {
            fetchSuggestions(query);
        }, 250);
    });
    
    // Update clear button visibility
    function updateClearButton() {
        const clearBtn = document.querySelector('.blog-search-modern-clear');
        if (clearBtn) {
            if (searchInput.value.trim().length > 0) {
                clearBtn.style.display = 'flex';
            } else {
                clearBtn.style.display = 'none';
            }
        }
    }
    
    // Clear button handler
    const clearBtn = document.querySelector('.blog-search-modern-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchInput.value = '';
            updateClearButton();
            suggestionsBox.classList.remove('active');
            suggestionsBox.innerHTML = '';
            searchInput.focus();
        });
        updateClearButton(); // Initialize on load
    }
    
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
                } else if (searchInput.value.trim().length > 0) {
                    searchForm.submit();
                }
                break;
            case 'Escape':
                suggestionsBox.classList.remove('active');
                break;
        }
    });
    
    function fetchSuggestions(query) {
        fetch('/admin/api/suggestions.php?q=' + encodeURIComponent(query), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Search failed');
                return response.json();
            })
            .then(data => {
                suggestions = Array.isArray(data) ? data : [];
                displaySuggestions(suggestions, query);
            })
            .catch(error => {
                console.error('Search error:', error);
                suggestionsBox.innerHTML = '<div style="padding: 16px; color: #999; text-align: center; font-size: 13px;">Search unavailable</div>';
                suggestionsBox.classList.add('active');
                positionDropdown();
            });
    }
    
    function displaySuggestions(data, query) {
        if (data.length === 0) {
            suggestionsBox.innerHTML = '<div style="padding: 16px; color: #999; text-align: center; font-size: 13px;">No articles found for "' + escapeHtml(query) + '"</div>';
            suggestionsBox.classList.add('active');
            positionDropdown();
            return;
        }
        
        suggestionsBox.innerHTML = data.map(item => `
            <a href="/blog/${item.slug}/" class="blog-suggestion-item">
                <div style="flex: 1;">
                    <div class="blog-suggestion-item-title">${highlightMatch(item.title, query)}</div>
                    <div class="blog-suggestion-item-excerpt">${item.excerpt ? escapeHtml(item.excerpt.substring(0, 80)) + '...' : 'No description'}</div>
                </div>
            </a>
        `).join('');
        
        suggestionsBox.classList.add('active');
        positionDropdown();
    }
    
    function positionDropdown() {
        const rect = searchInput.getBoundingClientRect();
        suggestionsBox.style.left = rect.left + 'px';
        suggestionsBox.style.top = (rect.bottom + window.scrollY + 8) + 'px';
        suggestionsBox.style.width = rect.width + 'px';
    }
    
    // Reposition dropdown on scroll and resize
    window.addEventListener('scroll', positionDropdown);
    window.addEventListener('resize', positionDropdown);
    
    function highlightMatch(text, query) {
        const escaped = escapeHtml(text);
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return escaped.replace(regex, '<mark>$1</mark>');
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, char => map[char]);
    }
    
    function updateSelection(items) {
        items.forEach((item, index) => {
            if (index === currentIndex) {
                item.style.backgroundColor = '#fafafa';
                item.style.borderLeftColor = 'var(--blog-accent)';
                searchInput.value = suggestions[index]?.title || searchInput.value;
            } else {
                item.style.backgroundColor = 'transparent';
                item.style.borderLeftColor = 'transparent';
            }
        });
    }
    
    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && !suggestionsBox.contains(e.target) && e.target !== clearBtn) {
            suggestionsBox.classList.remove('active');
        }
    });
    
    // Show suggestions on focus if input has text
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 1 && suggestionsBox.innerHTML) {
            suggestionsBox.classList.add('active');
            positionDropdown();
        }
    });
});
