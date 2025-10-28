                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-close modals on success and redirect to prevent form resubmission
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a success message
            const successAlert = document.querySelector('.alert-success');
            
            if (successAlert) {
                // Close all open modals immediately
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                });
                
                // Remove modal backdrop if it exists
                setTimeout(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 100);
                
                // Redirect to same page without POST data after a short delay
                setTimeout(() => {
                    window.location.href = window.location.pathname + window.location.search;
                }, 1500);
            }
        });
        
        // Prevent modal from reopening after form submission
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was loaded from cache, close any modals
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                });
            }
        });
    </script>
</body>
</html>
