<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Ihr Zugang zur Flüssigboden Akademie</title>
        <style type="text/css">
            body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 14px;
                line-height: 1.6;
                color: #333;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            #container {
                width: 100%;
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
            }
            #content {
                padding: 30px;
            }
            h1 {
                color: #2c5282;
                font-size: 24px;
                margin-bottom: 20px;
            }
            .credentials {
                background-color: #f7fafc;
                border-left: 4px solid #2c5282;
                padding: 15px;
                margin: 20px 0;
            }
            .credentials strong {
                display: block;
                margin-bottom: 5px;
            }
            .password {
                font-family: monospace;
                font-size: 16px;
                color: #c53030;
                background-color: #fff5f5;
                padding: 5px 10px;
                border-radius: 3px;
                display: inline-block;
                margin-top: 5px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                color: #718096;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <table id="container" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td id="content">
                    <h1>Willkommen bei der Flüssigboden Akademie</h1>
                    
                    <p>Hallo <% if $Member.FirstName %>$Member.FirstName<% else %>$Member.Email<% end_if %>,</p>
                    
                    <p>für Sie wurde ein Zugang zur Flüssigboden Akademie erstellt. Mit Ihren Zugangsdaten können Sie sich ab sofort in Ihrem persönlichen Bereich anmelden und auf Ihre gebuchten Kurse zugreifen.</p>
                    
                    <div class="credentials">
                        <strong>Ihre Zugangsdaten:</strong>
                        <p>
                            <strong>E-Mail:</strong> $Member.Email<br>
                            <strong>Passwort:</strong> <span class="password">$Password</span>
                        </p>
                    </div>
                    
                    <p><strong>Bitte ändern Sie Ihr Passwort nach der ersten Anmeldung.</strong></p>
                    
                    <p>Sie können sich unter folgendem Link anmelden:<br>
                    <a href="$LoginLink">$LoginLink</a></p>
                    
                    <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>
                    
                    <p>Mit freundlichen Grüßen<br>
                    Ihr Team der Flüssigboden Akademie</p>
                    
                    <div class="footer">
                        <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht direkt auf diese E-Mail.</p>
                    </div>
                </td>
            </tr>
        </table>
    </body>
</html>
