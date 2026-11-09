document.addEventListener("DOMContentLoaded", function () {
    const button = document.querySelector(".notification-button");
    const panel = document.querySelector(".notification-panel");

    if (!button || !panel) return;

    button.addEventListener("click", function (event) {
        event.stopPropagation();
        panel.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".notification-wrapper")) {
            panel.classList.remove("show");
        }
    });
});