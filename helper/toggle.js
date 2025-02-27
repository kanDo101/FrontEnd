function toggleTheme() {
    const currentTheme = document.body.getAttribute("data-theme");
    if (currentTheme === "dark") {
      document.body.removeAttribute("data-theme");

      document.querySelector(
        ".theme-toggle"
      ).innerHTML = `<i class="fas fa-moon"></i>`;
    } else {
      document.body.setAttribute("data-theme", "dark");
      document.querySelector(".theme-toggle").innerHTML =
        '<i class="fas fa-sun"></i>';
    }
  }