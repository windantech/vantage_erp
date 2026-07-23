$(document).ready(function () {
    let wasOffline = false; // track previous state
    let retryInterval = null; // for auto retry when coming back online

    function checkConnection(showBackOnline = true) {
        if (navigator.onLine) {
            $.ajax({
                url: "includes/ping/ping.php",
                method: "GET",
                timeout: 1000,
                success: function () {
                    if (wasOffline && showBackOnline) {
                        // only show if we were offline before
                        $(".internet-wraper")
                            .html("Back Online")
                            .css("display", "flex");

                        setTimeout(function () {
                            $(".internet-wraper").hide();
                        }, 1000);

                        wasOffline = false;
                    } else {
                        $(".internet-wraper").hide();
                    }

                    // clear retry loop once successful
                    if (retryInterval) {
                        clearInterval(retryInterval);
                        retryInterval = null;
                    }
                },
                error: function () {
                    showOffline();
                }
            });
        } else {
            showOffline();
        }
    }

    function showOffline() {
        $(".internet-wraper").css("display", "flex").html(`
            No Internet Connection
            <a href="" class="btn">Retry</a>
        `);
        wasOffline = true;
    }

    // Initial check
    checkConnection(false); // don’t show "Back Online" on page load

    // Listen for connection changes
    window.addEventListener("offline", () => {
        showOffline();
    });

    window.addEventListener("online", () => {
        // when browser thinks we are back online, start retrying until backend responds
        if (!retryInterval) {
            retryInterval = setInterval(() => checkConnection(true), 2000);
        }
    });

    // Retry button
    $(document).on("click", ".internet-wraper .btn", function (e) {
        e.preventDefault();
        checkConnection(true);
    });
});
