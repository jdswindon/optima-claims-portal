// --------------------------------------------------------------------------------------------------
// Mobile Menu - Opt 2
// --------------------------------------------------------------------------------------------------

// Set vars
const body = document.body;
const mobNav = document.querySelector(".mob-nav");
var allSubArrows = document.querySelectorAll(".sub-arrow");
var menuBtns = document.querySelectorAll('[data-mobile-menu-toggle]');

// Tabs
var tabBtns = document.querySelectorAll('.menu-tab-btn');

// Hide menu when close icon or underlay is clicked
var menuOverlay = document.querySelector(".mob-nav-underlay");

// Get all the sub nav arrow icons
var allArrows = document.querySelectorAll(".sub-arrow svg");

// Open current page tab
var currentPage = document.querySelector('.mob-nav .current-menu-item');

// Add active class to all sub nav arrow icons
allArrows.forEach((element) => {
  element.classList.add("active");
});

// For each sub nav, let it toggle a class and show/hide the sibling menu
allSubArrows.forEach((arrow) => {
  const submenu = arrow.parentElement.nextElementSibling;
  if (submenu) submenu.classList.add("hidden");
  arrow.addEventListener("click", e => {
    e.preventDefault();
    arrow.classList.toggle("active");
    arrow.parentElement.classList.toggle("active");
    if (submenu) submenu.classList.toggle("hidden");
    arrow.querySelectorAll("*").forEach(child => {
      child.classList.toggle("active");
    });
  });
});

// Show underlay and fix the body scroll when menu button is clicked
menuBtns.forEach(function (menuBtn) {
  menuBtn.addEventListener("click", function () {
    mobNav.classList.toggle("mob-nav--active");
    body.classList.toggle('overflow-hidden');
    document.querySelectorAll("[data-mobile-menu-toggle] .open").forEach(function (open) {
      open.classList.toggle("hidden");
    });
    document.querySelectorAll("[data-mobile-menu-toggle] .close").forEach(function (close) {
      close.classList.toggle("hidden");
    });
  });
});

if (tabBtns) {
  tabBtns.forEach(function (tabBtn) {
    tabBtn.addEventListener('click', function () {
      tabBtns.forEach(function (btn) {
        btn.classList.remove('active');
      });
      this.classList.add('active');
      document.querySelector('.menu-tab.active').classList.remove('active');
      var tab = document.querySelector('.menu-tab-' + this.dataset.menutab);
      tab.classList.add('active');
    });
  });
}

if (currentPage != null) {
  var currentPageParent = currentPage.parentElement;
  if (currentPageParent.classList == "sub-menu") {
    currentPageParent.style.display = "flex";
    var currentPageTab = currentPageParent.parentElement.parentElement;
  } else {
    var currentPageTab = currentPageParent;
  }

  var currentPageTabBtn = document.querySelector('[data-menutab="' + currentPageTab.dataset.menutabbtn + '"]');

  // Open tab
  if (currentPageTabBtn) {
    document.querySelector('.menu-tab-btn.active').classList.remove('active');
    currentPageTabBtn.classList.add('active');
    document.querySelector('.menu-tab.active').classList.remove('active');
    var tab = document.querySelector('.menu-tab-' + currentPageTabBtn.dataset.menutab);
    tab.classList.add('active');
  }
}

/*
// --------------------------------------------------------------------------------------------------
// Mobile Menu - Opt 1
// --------------------------------------------------------------------------------------------------

// Set vars
const body = document.body;
const mobNav = document.querySelector(".mob-nav");
var allSubArrows = document.querySelectorAll(".sub-arrow");
var menuBtns = document.querySelectorAll('[data-mobile-menu-toggle]');

// Get all the sub nav arrow icons
var allArrows = document.querySelectorAll(".sub-arrow svg");

// Open current page tab
var currentPage = document.querySelector('.mob-nav .current-menu-item');

// Hide menu when close icon or underlay is clicked
var menuOverlay = document.querySelector(".mob-nav-underlay");

// Add active class to all sub nav arrow icons
allArrows.forEach((element) => {
  element.classList.add("active");
});

// For each sub nav, let it toggle a class and show/hide the sibling menu
allSubArrows.forEach((arrow) => {
  const submenu = arrow.parentElement.nextElementSibling;
  if (submenu) submenu.classList.add("hidden");
  arrow.addEventListener("click", e => {
    e.preventDefault();
    arrow.classList.toggle("active");
    arrow.parentElement.classList.toggle("active");
    if (submenu) submenu.classList.toggle("hidden");
    arrow.querySelectorAll("*").forEach(child => {
      child.classList.toggle("active");
    });
  });
});

// Show underlay and fix the body scroll when menu button is clicked
menuBtns.forEach(function (menuBtn) {
  menuBtn.addEventListener("click", function () {
    mobNav.classList.toggle("mob-nav--active");
    body.classList.toggle('overflow-hidden');
    document.querySelectorAll("[data-mobile-menu-toggle] .open").forEach(function (open) {
      open.classList.toggle("hidden");
    });
    document.querySelectorAll("[data-mobile-menu-toggle] .close").forEach(function (close) {
      close.classList.toggle("hidden");
    });
  });
});

if (currentPage != null) {
  var currentPageParent = currentPage.parentElement;
  if (currentPageParent.classList == "sub-menu") {
    currentPageParent.style.display = "flex";
    var currentPageTab = currentPageParent.parentElement.parentElement;
  } else {
    var currentPageTab = currentPageParent;
  }
}
*/