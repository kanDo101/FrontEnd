
function toggleTheme() {
  const currentTheme = localStorage.getItem('theme') || 'light';
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
  
  // Save to localStorage
  localStorage.setItem('theme', newTheme);
  
  // Apply theme to document
  document.documentElement.setAttribute('data-theme', newTheme);
  document.body.setAttribute('data-theme', newTheme);
  
  // Update icon
  const themeToggleBtn = document.getElementById('themeToggle');
  if (themeToggleBtn) {
    const icon = themeToggleBtn.querySelector('i') || themeToggleBtn;
    if (newTheme === 'dark') {
      icon.className = icon.className.replace('fa-moon', 'fa-sun');
    } else {
      icon.className = icon.className.replace('fa-sun', 'fa-moon');
    }
  }
  
  // If the page has circles, update their colors
  if (typeof updateCircleColors === 'function') {
    updateCircleColors();
  }
}

// Apply saved theme when the DOM is loaded
function applyTheme() {
  const savedTheme = localStorage.getItem('theme') || 'light';
  
  // Apply theme to document
  document.documentElement.setAttribute('data-theme', savedTheme);
  document.body.setAttribute('data-theme', savedTheme);
  
  // Update icon if it exists
  const themeToggleBtn = document.getElementById('themeToggle');
  if (themeToggleBtn) {
    const icon = themeToggleBtn.querySelector('i') || themeToggleBtn;
    if (savedTheme === 'dark') {
      icon.className = icon.className.replace('fa-moon', 'fa-sun');
    } else {
      icon.className = icon.className.replace('fa-sun', 'fa-moon');
    }
  }
}

// Initialize theme on page load
document.addEventListener('DOMContentLoaded', applyTheme);