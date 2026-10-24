document.addEventListener("DOMContentLoaded", function () {

    var form = document.querySelector(".auth-form");
    var emailInput = document.querySelector("#email");
    var passwordInput = document.querySelector("#password");
    var status = document.querySelector("#status");
    var submitButton = document.querySelector(".go");
    var eyeButton = document.querySelector(".eye");

    if (!form) {
        console.error("Login form not found.");
        return;
    }

    console.log("PHP URL:", form.action);

    // Correct email validation
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;


    // --------------------------------------------------
    // STATUS MESSAGE
    // --------------------------------------------------

    function showStatus(message, isError) {

        if (!status) {
            return;
        }

        status.textContent = message;
        status.hidden = false;

        status.classList.toggle("error", !!isError);
        status.classList.toggle("success", !isError);
    }


    function clearStatus() {

        if (!status) {
            return;
        }

        status.textContent = "";
        status.hidden = true;

        status.classList.remove("error");
        status.classList.remove("success");
    }


    // --------------------------------------------------
    // SHOW / HIDE PASSWORD
    // --------------------------------------------------

    if (eyeButton && passwordInput) {

        eyeButton.addEventListener("click", function () {

            var isShowing = passwordInput.type === "password";

            if (isShowing) {

                passwordInput.type = "text";
                eyeButton.setAttribute("aria-pressed", "true");
                eyeButton.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {

                passwordInput.type = "password";
                eyeButton.setAttribute("aria-pressed", "false");
                eyeButton.setAttribute(
                    "aria-label",
                    "Show password"
                );
            }
        });
    }


    // --------------------------------------------------
    // CLEAR MESSAGE WHEN USER TYPES
    // --------------------------------------------------

    if (emailInput) {

        emailInput.addEventListener("input", function () {
            clearStatus();
        });
    }


    if (passwordInput) {

        passwordInput.addEventListener("input", function () {
            clearStatus();
        });
    }


    // --------------------------------------------------
    // LOGIN
    // --------------------------------------------------

    form.addEventListener("submit", function (event) {

        event.preventDefault();

        clearStatus();

        var email = emailInput
            ? emailInput.value.trim()
            : "";

        var password = passwordInput
            ? passwordInput.value
            : "";


        // --------------------------------------------------
        // VALIDATION
        // --------------------------------------------------

        if (!email) {

            showStatus(
                "Please enter your email address.",
                true
            );

            emailInput.focus();

            return;
        }


        if (!EMAIL_RE.test(email)) {

            showStatus(
                "Please enter a valid email address.",
                true
            );

            emailInput.focus();

            return;
        }


        if (!password) {

            showStatus(
                "Please enter your password.",
                true
            );

            passwordInput.focus();

            return;
        }


        // --------------------------------------------------
        // DISABLE LOGIN BUTTON
        // --------------------------------------------------

        if (submitButton) {
            submitButton.disabled = true;
        }

        showStatus("Signing in...", false);


        // --------------------------------------------------
        // SEND POST REQUEST
        // --------------------------------------------------

        fetch(form.action, {

            method: "POST",

            body: new FormData(form),

            credentials: "same-origin",

            headers: {
                "Accept": "application/json"
            }

        })

        .then(function (response) {

            console.log(
                "PHP RESPONSE STATUS:",
                response.status
            );

            return response.text();

        })

        .then(function (text) {

            console.log(
                "RAW PHP RESPONSE:",
                text
            );


            // --------------------------------------------------
            // CONVERT PHP RESPONSE TO JSON
            // --------------------------------------------------

            var data;

            try {

                data = JSON.parse(text);

            } catch (error) {

                console.error(
                    "PHP DID NOT RETURN VALID JSON:",
                    error
                );

                showStatus(
                    "Server returned an invalid response.",
                    true
                );

                if (submitButton) {
                    submitButton.disabled = false;
                }

                return;
            }


            console.log(
                "PHP RESPONSE DATA:",
                data
            );


            // --------------------------------------------------
            // LOGIN SUCCESS
            // --------------------------------------------------

            if (data.ok === true) {

                console.log("LOGIN SUCCESSFUL");

                console.log(
                    "REDIRECT:",
                    data.redirect
                );


                showStatus(
                    "Login successful. Redirecting...",
                    false
                );


                // Redirect to dashboard
                if (data.redirect) {

                    window.location.href = data.redirect;

                } else {

                    console.error(
                        "PHP did not provide a redirect."
                    );

                    showStatus(
                        "Login successful, but no dashboard was specified.",
                        true
                    );

                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }

                return;
            }


            // --------------------------------------------------
            // LOGIN FAILED
            // --------------------------------------------------

            showStatus(
                data.message ||
                "Incorrect email or password.",
                true
            );


            if (submitButton) {
                submitButton.disabled = false;
            }

        })

        .catch(function (error) {

            console.error(
                "LOGIN REQUEST ERROR:",
                error
            );

            showStatus(
                "Unable to connect to the server. Please try again.",
                true
            );

            if (submitButton) {
                submitButton.disabled = false;
            }
        });

    });
});
