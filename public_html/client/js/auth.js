/**
 * Login / register UI wired to legacy PHP endpoints.
 */
var AnimasterAuth = (function ()
{
    var onSuccess = null;
    var activeTab = 'login';

    var authScreen = null;
    var loginForm = null;
    var registerForm = null;
    var errorEl = null;
    var successEl = null;
    var loginSubmit = null;
    var registerSubmit = null;

    function init(options)
    {
        onSuccess = options.onSuccess;

        authScreen = document.getElementById('auth-screen');
        loginForm = document.getElementById('login-form');
        registerForm = document.getElementById('register-form');
        errorEl = document.getElementById('auth-error');
        successEl = document.getElementById('auth-success');
        loginSubmit = document.getElementById('login-submit');
        registerSubmit = document.getElementById('register-submit');

        document.querySelectorAll('.auth-tab').forEach(function (tabBtn)
        {
            tabBtn.addEventListener('click', function ()
            {
                switchTab(tabBtn.getAttribute('data-tab'));
            });
        });

        loginForm.addEventListener('submit', handleLogin);
        registerForm.addEventListener('submit', handleRegister);
    }

    function switchTab(tab)
    {
        activeTab = tab;
        clearMessages();

        document.querySelectorAll('.auth-tab').forEach(function (tabBtn)
        {
            var isActive = tabBtn.getAttribute('data-tab') === tab;
            tabBtn.classList.toggle('active', isActive);
            tabBtn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        document.querySelectorAll('.auth-pane').forEach(function (pane)
        {
            var isLogin = pane.id === 'auth-login';
            var isActive = (tab === 'login' && isLogin) || (tab === 'register' && !isLogin);
            pane.classList.toggle('active', isActive);
            pane.hidden = !isActive;
        });
    }

    function clearMessages()
    {
        errorEl.hidden = true;
        errorEl.textContent = '';
        successEl.hidden = true;
        successEl.textContent = '';
    }

    function showError(message)
    {
        successEl.hidden = true;
        errorEl.textContent = message;
        errorEl.hidden = false;
    }

    function showSuccess(message)
    {
        errorEl.hidden = true;
        successEl.textContent = message;
        successEl.hidden = false;
    }

    function setBusy(form, submitBtn, busy)
    {
        submitBtn.disabled = busy;
        form.querySelectorAll('input').forEach(function (input)
        {
            input.disabled = busy; 
        });
        submitBtn.textContent = busy
            ? (form === loginForm ? 'Signing in…' : 'Creating account…')
            : (form === loginForm ? 'Enter world' : 'Create account');
    }

    function handleLogin(e)
    {
        e.preventDefault();
        clearMessages();

        var username = document.getElementById('login-username').value.trim();
        var password = document.getElementById('login-password').value;

        if (!username || !password)
        {
            showError('Username and password are required.');
            return;
        }

        setBusy(loginForm, loginSubmit, true);

        AnimasterApi.login(username, password).then(function (data)
        {
            hideAuthScreen();

            if (onSuccess)
            {
                onSuccess(data.profile, data.battle);
            }
        }).catch(function (err)
        {
            showError(err.message || 'Login failed');
        }).finally(function ()
        {
            setBusy(loginForm, loginSubmit, false);
        });
    }

    function handleRegister(e)
    {
        e.preventDefault();
        clearMessages();

        var username = document.getElementById('register-username').value.trim();
        var displayName = document.getElementById('register-display-name').value.trim();
        var email = document.getElementById('register-email').value.trim();
        var password = document.getElementById('register-password').value;
        var confirm = document.getElementById('register-password-confirm').value;

        if (!username || !displayName || !email || !password)
        {
            showError('All fields are required.');
            return;
        }

        if (password !== confirm)
        {
            showError('Passwords do not match.');
            return;
        }

        setBusy(registerForm, registerSubmit, true);

        AnimasterApi.register({
            username: username,
            display_name: displayName,
            email: email,
            password: password
        }).then(function ()
        {
            registerForm.reset();
            switchTab('login');
            document.getElementById('login-username').value = username;
            document.getElementById('login-password').value = password;
            showSuccess('Account created. Signing you in…');

            return AnimasterApi.login(username, password);
        }).then(function (data)
        {
            if (!data)
            {
                return;
            }

            hideAuthScreen();

            if (onSuccess)
            {
                onSuccess(data.profile, data.battle);
            }
        }).catch(function (err)
        {
            var username = document.getElementById('login-username').value.trim();

            if (username && activeTab === 'login')
            {
                showError('Account created, but sign-in failed: ' + (err.message || 'try logging in manually.'));
            }
            else
            {
                showError(err.message || 'Registration failed');
            }
        }).finally(function ()
        {
            setBusy(registerForm, registerSubmit, false);
        });
    }

    function hideAuthScreen()
    {
        authScreen.hidden = true;
    }

    return {
        init: init
    };
})();
