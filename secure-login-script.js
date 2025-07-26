(function ($) {
    'use strict';

    $(document).ready(function () {
        const $loginForm = $('#loginform');

        if ($loginForm.length > 0 && typeof sle_login_data !== 'undefined' && sle_login_data.public_key) {
            
            $loginForm.on('submit', function (e) {
                e.preventDefault();

                const username = $('#user_login').val();
                const password = $('#user_pass').val();

                if (!username || !password) {
                    $loginForm.get(0).submit();
                    return;
                }

                const encrypt = new JSEncrypt();
                encrypt.setPublicKey(sle_login_data.public_key);

                const encryptedUsername = encrypt.encrypt(username);
                const encryptedPassword = encrypt.encrypt(password);
                
                if (!encryptedUsername || !encryptedPassword) {
                    $loginForm.get(0).submit();
                    return;
                }

                $('#user_login').val(encryptedUsername);
                $('#user_pass').val(encryptedPassword);
                
                $loginForm.append('<input type="hidden" name="sle_encrypted" value="true" />');

                $loginForm.get(0).submit();
            });
        }
    });

})(jQuery);