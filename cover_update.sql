-- (Tùy chọn) Ghi tên file ảnh bìa vào cột books.cover_image.
-- Website vẫn tự nhận ảnh theo slug nên KHÔNG bắt buộc chạy file này.
USE bookstore;
UPDATE books SET cover_image = 'mat-biec.jpg'                      WHERE slug = 'mat-biec';
UPDATE books SET cover_image = 'cho-toi-xin-mot-ve-di-tuoi-tho.jpg' WHERE slug = 'cho-toi-xin-mot-ve-di-tuoi-tho';
UPDATE books SET cover_image = 'chi-pheo.webp'                     WHERE slug = 'chi-pheo';
UPDATE books SET cover_image = 'de-men-phieu-luu-ky.webp'          WHERE slug = 'de-men-phieu-luu-ky';
UPDATE books SET cover_image = 'dac-nhan-tam.jpg'                  WHERE slug = 'dac-nhan-tam';
