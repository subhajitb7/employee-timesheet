// Modern Employee Timesheet UI - Enhanced Interactions
// Handles: select-all checkboxes, modal open/close, micro-interactions, and UI enhancements

document.addEventListener('DOMContentLoaded', function () {
  // SELECT ALL checkbox behavior with smooth animation
  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      const checkboxes = document.querySelectorAll('.timesheet-checkbox');
      checkboxes.forEach((cb, index) => {
        setTimeout(() => {
          cb.checked = selectAll.checked;
          cb.parentElement.style.transform = 'scale(1.1)';
          setTimeout(() => {
            cb.parentElement.style.transform = 'scale(1)';
          }, 150);
        }, index * 20);
      });
    });

    // Uncheck "select all" if any individual is unchecked
    document.addEventListener('change', function (e) {
      if (e.target && e.target.classList && e.target.classList.contains('timesheet-checkbox')) {
        const all = document.querySelectorAll('.timesheet-checkbox');
        const checked = document.querySelectorAll('.timesheet-checkbox:checked');
        if (all.length && checked.length !== all.length) {
          selectAll.checked = false;
        } else if (all.length && checked.length === all.length) {
          selectAll.checked = true;
        }
      }
    });
  }

  // MODAL logic with smooth animations
  const modal = document.getElementById('reviewModal');
  const modalDetails = document.getElementById('modalDetails');
  const modalTimesheetId = document.getElementById('modalTimesheetId');
  const modalRemarks = document.getElementById('modalRemarks');

  // Expose openModal globally (used inline from PHP)
  window.openModal = function (id, employee, project, description, remarks) {
    if (!modal) return;
    modalTimesheetId.value = id || '';
    modalRemarks.value = remarks || '';
    modalDetails.innerHTML = `
      <div style="margin-bottom: 1.5rem;">
        <p style="margin-bottom: 0.75rem;"><strong style="color: var(--text);">Employee:</strong> <span style="color: var(--text-muted);">${escapeHtml(employee || '')}</span></p>
        <p style="margin-bottom: 0.75rem;"><strong style="color: var(--text);">Project:</strong> <span style="color: var(--text-muted);">${escapeHtml(project || '')}</span></p>
        <p><strong style="color: var(--text);">Description:</strong> <span style="color: var(--text-muted);">${escapeHtml(description || '')}</span></p>
      </div>
    `;
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    // Focus textarea for quick remark entry
    setTimeout(() => {
      modalRemarks.focus();
      modalRemarks.select();
    }, 150);
  };

  window.closeModal = function () {
    if (!modal) return;
    modal.style.display = 'none';
    document.body.style.overflow = '';
    modalTimesheetId.value = '';
    modalRemarks.value = '';
    modalDetails.innerHTML = '';
  };

  // Close modal when clicking outside content or pressing ESC
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.style.display === 'flex') {
        closeModal();
      }
    });
  }

  // Small helper to escape HTML for modal insertion
  function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Improve forms: confirm bulk action if none selected
  const bulkForm = document.getElementById('bulkForm');
  if (bulkForm) {
    bulkForm.addEventListener('submit', function (e) {
      const action = bulkForm.querySelector('select[name="bulk_action"]');
      const selected = bulkForm.querySelectorAll('.timesheet-checkbox:checked');
      if (!action || !action.value) {
        alert('Please choose a bulk action first.');
        e.preventDefault();
        return;
      }
      if (selected.length === 0) {
        alert('Please select at least one timesheet to apply the bulk action.');
        e.preventDefault();
        return;
      }
      // Confirm for destructive operation "reject"
      if (action.value === 'reject' && !confirm('Are you sure you want to reject the selected timesheets?')) {
        e.preventDefault();
      }
    });
  }

  // Auto-dismiss success messages after 5 seconds
  const successMessages = document.querySelectorAll('.success');
  successMessages.forEach(msg => {
    setTimeout(() => {
      msg.style.opacity = '0';
      msg.style.transform = 'translateY(-10px)';
      setTimeout(() => {
        msg.remove();
      }, 300);
    }, 5000);
  });

  // Add smooth hover effects to buttons
  const buttons = document.querySelectorAll('.btn');
  buttons.forEach(btn => {
    btn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
    });
    btn.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });

  // Add ripple effect to buttons on click
  buttons.forEach(btn => {
    btn.addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = x + 'px';
      ripple.style.top = y + 'px';
      ripple.classList.add('ripple');
      
      this.appendChild(ripple);
      
      setTimeout(() => {
        ripple.remove();
      }, 600);
    });
  });

  // Add smooth scroll to top for long pages
  const scrollToTopBtn = document.createElement('button');
  scrollToTopBtn.innerHTML = '↑';
  scrollToTopBtn.className = 'scroll-to-top';
  scrollToTopBtn.style.cssText = `
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: var(--shadow-lg);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 999;
  `;
  document.body.appendChild(scrollToTopBtn);

  window.addEventListener('scroll', function() {
    if (window.pageYOffset > 300) {
      scrollToTopBtn.style.opacity = '1';
      scrollToTopBtn.style.visibility = 'visible';
    } else {
      scrollToTopBtn.style.opacity = '0';
      scrollToTopBtn.style.visibility = 'hidden';
    }
  });

  scrollToTopBtn.addEventListener('click', function() {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  // Add loading state to forms on submit
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
    form.addEventListener('submit', function() {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn && !submitBtn.disabled) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Processing...';
        
        // Re-enable after 10 seconds as fallback
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.style.opacity = '1';
          submitBtn.style.cursor = 'pointer';
          submitBtn.textContent = originalText;
        }, 10000);
      }
    });
  });

  // Add smooth table row animations
  const tableRows = document.querySelectorAll('.data-table tbody tr');
  tableRows.forEach((row, index) => {
    row.style.opacity = '0';
    row.style.transform = 'translateY(10px)';
    setTimeout(() => {
      row.style.transition = 'all 0.3s ease';
      row.style.opacity = '1';
      row.style.transform = 'translateY(0)';
    }, index * 50);
  });

  // Enhance input focus states
  const inputs = document.querySelectorAll('input, select, textarea');
  inputs.forEach(input => {
    input.addEventListener('focus', function() {
      this.parentElement.style.transform = 'scale(1.02)';
      setTimeout(() => {
        this.parentElement.style.transform = 'scale(1)';
      }, 200);
    });
  });

  // Add smooth page transitions
  const links = document.querySelectorAll('a[href^="/"]');
  links.forEach(link => {
    link.addEventListener('click', function(e) {
      if (this.href && !this.target && !this.hasAttribute('download')) {
        const href = this.getAttribute('href');
        if (href && !href.includes('#') && !href.includes('javascript:')) {
          // Add loading state
          document.body.style.opacity = '0.8';
          document.body.style.transition = 'opacity 0.2s ease';
        }
      }
    });
  });
});

// Add CSS for ripple effect
const style = document.createElement('style');
style.textContent = `
  .btn {
    position: relative;
    overflow: hidden;
  }
  
  .ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple-animation 0.6s ease-out;
    pointer-events: none;
  }
  
  @keyframes ripple-animation {
    to {
      transform: scale(4);
      opacity: 0;
    }
  }
  
  .scroll-to-top:hover {
    transform: translateY(-4px) !important;
    box-shadow: var(--shadow-xl) !important;
  }
`;
document.head.appendChild(style);
