from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import parse_qs
import mysql.connector
import re
import os
from dotenv import load_dotenv

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
load_dotenv(os.path.join(BASE_DIR, 'data', '.env'))

DB_CONFIG = {
    'host':     os.getenv('DB_HOST'),
    'user':     os.getenv('DB_USER'),
    'password': os.getenv('DB_PASSWORD'),
    'database': os.getenv('DB_NAME'),
}

def validate(data, languages):
    errors = []

    fullname = data.get('fullname', '').strip()
    if not fullname:
        errors.append('ФИО обязательно для заполнения')
    elif len(fullname) < 5:
        errors.append('ФИО слишком короткое (минимум 5 символов)')
    elif not re.match(r'^[А-ЯЁа-яёA-Za-z\s\-]+$', fullname):
        errors.append('ФИО должно содержать только буквы')

    phone = data.get('phone', '').strip()
    if not phone:
        errors.append('Телефон обязателен для заполнения')
    elif not re.match(r'^[\+]?[\d\s\-\(\)]{7,20}$', phone):
        errors.append('Телефон указан неверно (пример: +7 999 123-45-67)')

    email = data.get('email', '').strip()
    if not email:
        errors.append('E-mail обязателен для заполнения')
    elif not re.match(r'^[^@\s]+@[^@\s]+\.[^@\s]+$', email):
        errors.append('E-mail указан неверно')

    birthdate = data.get('birthdate', '').strip()
    if not birthdate:
        errors.append('Дата рождения обязательна')
    else:
        try:
            from datetime import date
            parts = birthdate.split('-')
            birth = date(int(parts[0]), int(parts[1]), int(parts[2]))
            today = date.today()
            age = (today - birth).days // 365
            if int(parts[0]) < 1900:
                errors.append('Дата рождения некорректна')
            elif age < 5:
                errors.append('Слишком маленький возраст')
            elif age > 120:
                errors.append('Дата рождения некорректна')
        except:
            errors.append('Дата рождения указана неверно')

    gender = data.get('gender', '').strip()
    if not gender:
        errors.append('Укажите пол')
    elif gender not in ('male', 'female'):
        errors.append('Пол указан некорректно')

    if not languages:
        errors.append('Выберите хотя бы один язык программирования')

    bio = data.get('bio', '').strip()
    if not bio:
        errors.append('Биография обязательна')
    elif len(bio) < 10:
        errors.append('Биография слишком короткая (минимум 10 символов)')

    contract = data.get('contract', '')
    if not contract:
        errors.append('Необходимо подтвердить ознакомление с контрактом')

    return errors


def save_to_db(data, languages):
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()

    try:
        cursor.execute("""
            INSERT INTO users (fullname, phone, email, birthdate, gender, bio, contract)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (
            data.get('fullname', '').strip(),
            data.get('phone', '').strip(),
            data.get('email', '').strip(),
            data.get('birthdate') or None,
            data.get('gender', '').strip(),
            data.get('bio', '').strip(),
            1 if data.get('contract') else 0
        ))

        user_id = cursor.lastrowid

        for lang in languages:
            cursor.execute("""
                INSERT INTO user_languages (user_id, language)
                VALUES (%s, %s)
            """, (user_id, lang))

        conn.commit()

    except Exception as e:
        conn.rollback()
        raise e
    finally:
        cursor.close()
        conn.close()


def error_page(errors):
    items = ''.join(f'<li>{e}</li>' for e in errors)
    return f'''
    <html>
    <head>
      <meta charset="utf-8"/>
      <title>Ошибка</title>
      <style>
        body {{ font-family: sans-serif; background: #f8f8f6; display: flex;
               align-items: center; justify-content: center; min-height: 100vh; margin: 0; }}
        .box {{ background: white; border: 1px solid #d0d0ca; padding: 40px 48px;
                max-width: 480px; width: 100%; }}
        h2 {{ font-size: 22px; margin-bottom: 20px; color: #0a0a0a; }}
        ul {{ padding-left: 20px; color: #c0392b; line-height: 2; font-size: 14px; }}
        a {{ display: inline-block; margin-top: 28px; font-size: 12px; letter-spacing: 0.1em;
             text-transform: uppercase; color: #0a0a0a; text-decoration: none;
             border-bottom: 1px solid #0a0a0a; padding-bottom: 2px; }}
      </style>
    </head>
    <body>
      <div class="box">
        <h2>Пожалуйста, исправьте ошибки</h2>
        <ul>{items}</ul>
        <a href="/">&#8592; Вернуться к форме</a>
      </div>
    </body>
    </html>
    '''.encode('utf-8')


class Handler(BaseHTTPRequestHandler):

    def do_GET(self):
        if self.path in ('/', '/form.html'):
            self.send_response(200)
            self.send_header('Content-type', 'text/html; charset=utf-8')
            self.end_headers()
            with open('form.html', 'rb') as f:
                self.wfile.write(f.read())
        elif self.path == '/success':
            self.send_response(200)
            self.send_header('Content-type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write('''
                <html>
                <head><meta charset="utf-8"/><title>Успех</title>
                <style>
                  body { font-family: sans-serif; background: #f8f8f6;
                         display: flex; align-items: center; justify-content: center;
                         min-height: 100vh; margin: 0; }
                  .box { text-align: center; }
                  h2 { font-size: 28px; margin-bottom: 16px; }
                  a { font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
                      color: #0a0a0a; text-decoration: none; border-bottom: 1px solid #0a0a0a; }
                </style>
                </head>
                <body><div class="box">
                  <h2>&#10003; Данные сохранены!</h2>
                  <a href="/">&#8592; Назад к форме</a>
                </div></body>
                </html>
            '''.encode('utf-8'))
        else:
            self.send_response(404)
            self.end_headers()

    def do_POST(self):
        length = int(self.headers.get('Content-Length', 0))
        body = self.rfile.read(length).decode('utf-8')
        fields = parse_qs(body)

        data = {k: v[0] for k, v in fields.items()}
        languages = fields.get('languages', [])

        errors = validate(data, languages)

        if errors:
            self.send_response(400)
            self.send_header('Content-type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(error_page(errors))
            return

        try:
            save_to_db(data, languages)
            self.send_response(303)
            self.send_header('Location', '/success')
            self.end_headers()
        except Exception as e:
            self.send_response(500)
            self.send_header('Content-type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(f'<h2>Ошибка БД: {e}</h2>'.encode('utf-8'))

    def log_message(self, format, *args):
        print(f"[{self.address_string()}] {format % args}")


if __name__ == '__main__':
    server = HTTPServer(('localhost', 8000), Handler)
    print('Сервер запущен: http://localhost:8000')
    server.serve_forever()