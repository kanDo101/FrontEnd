function toggleTheme() {
  const currentTheme = document.body.getAttribute("data-theme");

  if (currentTheme === "dark") {
      document.body.removeAttribute("data-theme");
      localStorage.setItem("theme", "light");
      document.querySelector(".theme-toggle").innerHTML = `<i class="fas fa-moon"></i>`;
  } else {
      document.body.setAttribute("data-theme", "dark");
      localStorage.setItem("theme", "dark");
      document.querySelector(".theme-toggle").innerHTML = `<i class="fas fa-sun"></i>`;
  }
}

// Apply theme on page load
document.addEventListener("DOMContentLoaded", function () {
  const savedTheme = localStorage.getItem("theme");

  if (savedTheme === "dark") {
      document.body.setAttribute("data-theme", "dark");
      document.querySelector(".theme-toggle").innerHTML = `<i class="fas fa-sun"></i>`;
  }
});
