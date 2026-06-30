console.log("✅ scriptFront.js is LOADED!");

document.addEventListener("DOMContentLoaded", function () {
    const navMenu = document.querySelector(".nav-menu");
    const menuToggle = document.querySelector(".menu-toggle");

    menuToggle.addEventListener("click", function (e) {
        e.preventDefault();
        navMenu.classList.toggle("active");
    });

    const gridItems = document.querySelectorAll(".grid-item");
    const expandedView = document.getElementById("expandedView");
    const expandedTitle = document.getElementById("expandedTitle");
    const expandedIcon = document.getElementById("expandedIcon");
    const closeBtn = document.querySelector(".close-btn");
    const downloadButton = document.getElementById("downloadApp");
    const installGuideBtn = document.getElementById("installGuide");
    const usageGuideBtn = document.getElementById("usageGuide");

    let currentApp = {};

    gridItems.forEach((item) => {
        item.addEventListener("click", function () {
            const appName = this.getAttribute("data-name");
            const appLink = this.getAttribute("data-link");
            const appIcon = this.getAttribute("data-icon");
            const installGuide = this.getAttribute("data-install");
            const usageGuide = this.getAttribute("data-usage");

            // fill expanded view with app details
            expandedTitle.textContent = appName;
            expandedIcon.src = appIcon;

            currentApp = {
                link: appLink,
                installGuide,
                usageGuide
            };

            // show/hide buttons
            installGuideBtn.style.display = installGuide ? "inline-block" : "none";
            usageGuideBtn.style.display = usageGuide ? "inline-block" : "none";
            downloadButton.style.display = appLink ? "inline-block" : "none";

            expandedView.style.display = "flex";
            document.body.style.overflow = "hidden";
        });
    });

    // btn for download — navigate to the stable link; the server streams the
    // active version's APK with its real (versioned) filename via Content-Disposition.
    downloadButton.addEventListener("click", function () {
        if (currentApp.link) {
            window.location.href = currentApp.link;
        }
    });

    // btn for install guide
    installGuideBtn.addEventListener("click", function () {
        if (currentApp.installGuide) {
            triggerDownload(currentApp.installGuide);
        }
    });

    // btn for guide
    usageGuideBtn.addEventListener("click", function () {
        if (currentApp.usageGuide) {
            triggerDownload(currentApp.usageGuide);
        }
    });

    // closeBtn
    closeBtn.addEventListener("click", function () {
        expandedView.style.display = "none";
        document.body.style.overflow = "auto";
    });

    // close expandedView on clicking outside of it
    expandedView.addEventListener("click", function (e) {
        if (e.target === expandedView) {
            expandedView.style.display = "none";
            document.body.style.overflow = "auto";
        }
    });

    function triggerDownload(url) {
        const filename = url.split('/').pop().split('?')[0];
        const link = document.createElement("a");
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
});
