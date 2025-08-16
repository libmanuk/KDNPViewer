    document.addEventListener('DOMContentLoaded', function() {
        // Delay the scroll to allow other scripts to finish executing
        setTimeout(function() {
            const headerElement = document.getElementById('ukl-header');
            if (headerElement) {
                headerElement.scrollIntoView({ behavior: 'smooth' });
            }
        }, 300); // Adjust the delay as needed (in milliseconds)
    });

    window.onload = function () {
        const handToolButton = document.getElementById("cursorHandTool");
        const spreadNoneButton = document.getElementById("spreadNone");

        if (handToolButton) {
            handToolButton.click();
        }

        if (spreadNoneButton) {
            spreadNoneButton.click();
        }
    };