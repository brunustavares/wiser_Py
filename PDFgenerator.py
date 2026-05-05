#
# wiser.Py
# Python app for integrated data and indicators management
# related to the tests carried out in WISEflow and Moodle.
# (developed for UAb - Universidade Aberta)
#
# @package    wiser.Py
# @category   app
# @author     Bruno Tavares <brunustavares@gmail.com>
# @link       https://www.linkedin.com/in/brunomastavares/
# @copyright  Copyright (C) 2024-present Bruno Tavares
# @license    GNU General Public License v3 or later
#             https://www.gnu.org/licenses/gpl-3.0.html
# @version    2026042903
# @date       2026-02-06
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.
#

import re
from reportlab.platypus import (
    SimpleDocTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    Image,
    Flowable
)
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import A4, landscape, portrait
from reportlab.lib.styles import (
    getSampleStyleSheet,
    ParagraphStyle
)
from reportlab.lib.enums import (
    TA_CENTER,
    TA_LEFT
)
from reportlab.lib.units import mm
from reportlab.lib import colors
from typing import Iterable
from PIL import Image as PILImage
from pypdf import PdfWriter
from datetime import datetime
from pathlib import Path


# classe: moldura de contornos redondos
class RoundedFrame(Flowable):
    def __init__(
        self,
        content,
        width,
        padding=10,
        radius=8,
        stroke_width=0.75,
        stroke_color=colors.grey,
        fill_color=None,
    ):
        super().__init__()
        self.content = content
        self.width = width
        self.padding = padding
        self.radius = radius
        self.stroke_width = stroke_width
        self.stroke_color = stroke_color
        self.fill_color = fill_color

        self.table = Table(
            [[content]],
            colWidths=[width - 2 * padding]
        )

        self.table.setStyle([
            ("LEFTPADDING", (0, 0), (-1, -1), 0),
            ("RIGHTPADDING", (0, 0), (-1, -1), 0),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ])

    def wrap(self, availWidth, availHeight):
        w, h = self.table.wrap(availWidth, availHeight)
        self.height = h + 2 * self.padding
        return self.width, self.height

    def draw(self):
        canvas = self.canv

        canvas.saveState()
        canvas.setLineWidth(self.stroke_width)
        canvas.setStrokeColor(self.stroke_color)

        if self.fill_color:
            canvas.setFillColor(self.fill_color)
        else:
            canvas.setFillColor(colors.transparent)

        canvas.roundRect(
            0,
            0,
            self.width,
            self.height,
            self.radius,
            stroke=1,
            fill=1 if self.fill_color else 0
        )

        canvas.translate(self.padding, self.padding)
        self.table.drawOn(canvas, 0, 0)

        canvas.restoreState()
        
        
# cabeçalho com logótipo
def header_logo(doc):
    LOGO_HEIGHT = 22 * mm

    uab_logo = "./static/img/UAb.png"

    logo = Image(uab_logo)
    ratio = logo.imageWidth / logo.imageHeight
    logo.drawHeight = LOGO_HEIGHT
    logo.drawWidth = LOGO_HEIGHT * ratio

    table = Table(
        [[logo, ""]],
        colWidths=[doc.width / 2, doc.width / 2]
    )

    table.setStyle(TableStyle([
        ("ALIGN", (0, 0), (0, 0), "LEFT"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 20),
    ]))

    return table


#  implementação da moldura de contornos redondos
def rounded_framed_block(
    flowables,
    doc,
    padding=10,
    bottom_padding=20,
    radius=8,
    space_after=20
):
    content = flowables + [Spacer(1, bottom_padding)]

    frame = RoundedFrame(
        content=content,
        width=doc.width,
        padding=padding,
        radius=radius
    )

    return [frame, Spacer(1, space_after)]


# marca-dágua no rodapé
def watermark(canvas, doc):
    canvas.saveState()
    canvas.setFont("Helvetica", 9)
    canvas.setFillGray(0.90)

    line1 = "PDF gerado no wiser.Py"
    line2 = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    line1_width = canvas.stringWidth(line1, "Helvetica", 9)
    line2_width = canvas.stringWidth(line2, "Helvetica", 9)

    x = doc.pagesize[0] - 40
    x1 = x - line1_width
    x2 = x - line2_width
    y1 = 30
    y2 = 20

    text = canvas.beginText()
    text.setFont("Helvetica", 9)
    text.setTextOrigin(x1, y1)
    text.textLine(line1)
    text.setTextOrigin(x2, y2)
    text.textLine(line2)

    canvas.drawText(text)
    canvas.restoreState()


# geração da folha de rosto
def generate_CoverSheet(
    output_path: str,
    system_name: str,
    timezone: str,
    title: str,
    subtitle: str,
    season: str,
    dtfrom: str,
    dtto: str,
    scale: str,
    type: str,
    assessor: str,
    student_name: str,
    student_number: str,
    student_grade: str
):
    doc = SimpleDocTemplate(
        output_path,
        pagesize=A4,
        rightMargin=50,
        leftMargin=50,
        topMargin=40,
        bottomMargin=50
    )

    styles = getSampleStyleSheet()

    styles.add(ParagraphStyle(
        name="csHeader",
        alignment=TA_CENTER,
        fontSize=10,
        textColor=colors.grey
    ))

    styles.add(ParagraphStyle(
        name="csTitle",
        alignment=TA_CENTER,
        fontSize=18,
        leading=22,
        spaceAfter=8
    ))

    styles.add(ParagraphStyle(
        name="csSubtitle",
        alignment=TA_CENTER,
        fontSize=14,
        spaceAfter=6
    ))

    styles.add(ParagraphStyle(
        name="csSectionTitle",
        fontSize=12,
        spaceBefore=20,
        spaceAfter=10,
        underline=True
    ))

    styles.add(ParagraphStyle(
        name="csNormalLeft",
        fontSize=11,
        alignment=TA_LEFT,
        spaceAfter=6
    ))

    sheet = []

    # logótipo
    sheet.append(header_logo(doc))
    sheet.append(Spacer(1, 45))

    # metadados
    sheet.append(Paragraph(system_name, styles["csHeader"]))
    sheet.append(Paragraph(timezone, styles["csHeader"]))
    sheet.append(Spacer(1, 75))

    # identificação da prova
    sheet.extend(
        rounded_framed_block(
            [
                Paragraph(title, styles["csTitle"]),
                Paragraph(season, styles["csSubtitle"]),
                Paragraph(subtitle, styles["csSubtitle"]),
            ],
            doc,
            padding=10,
            bottom_padding=5,
            radius=10
        )
    )    

    # dados da prova
    info_table = Table(
        [
            ["Data de início:", dtfrom],
            ["Data de fim:", dtto],
            ["Escala de avaliação:", scale],
            ["Tipologia:", type],
            ["Docente:", assessor],
        ],
        colWidths=[160, doc.width - 400]
    )

    info_table.setStyle(TableStyle([
        ("FONT", (0, 0), (0, -1), "Helvetica-Bold"),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]))

    sheet.append(info_table)
    sheet.append(Spacer(1, 180))

    # identificação do estudante
    sheet.append(Paragraph("Estudante", styles["csSectionTitle"]))
    sheet.append(Paragraph(f"<b>Nome: </b> {student_name}", styles["csNormalLeft"]))
    sheet.append(Paragraph(f"<b>Número: </b> {student_number}", styles["csNormalLeft"]))
    sheet.append(Paragraph(f"<b>Nota: </b> {student_grade}", styles["csNormalLeft"]))

    doc.build(
        sheet,
        onFirstPage=watermark,
        onLaterPages=watermark
    )


# formatação da IdUC em PDF
def format_IdUC(output_path: str, fields: dict, data: dict):
    doc = SimpleDocTemplate(
        output_path,
        pagesize=A4,
        rightMargin=50,
        leftMargin=50,
        topMargin=40,
        bottomMargin=50
    )

    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(name="ucTitle", fontSize=16, leading=20, spaceAfter=10))
    styles.add(ParagraphStyle(name="sectionHeader", fontSize=13, spaceBefore=15, spaceAfter=8))
    styles.add(ParagraphStyle(name="label", fontSize=10, textColor=colors.grey))

    sheet = []

    def val(key):
        return str(data.get(key, "") or "")

    def add_if_value(story, label, value, suffix=""):
        if value not in ("", "0", None):
            story.append(Paragraph(f"<b>{label}: </b> {value}{suffix}", styles["Normal"]))

    def add_colored_box_to_story(story, flowables, color):
        if not flowables:
            return
        
        frame = RoundedFrame(
            content=flowables,
            width=doc.width - 20,   # smaller so it fits inside parent rounded block
            padding=10,
            radius=6,
            fill_color=color,
            stroke_color=colors.lightgrey
        )

        story.append(frame)
        story.append(Spacer(1, 8))

    # cabeçalho com logótipo
    sheet.append(header_logo(doc))
    sheet.append(Spacer(1, 20))

    sheet.append(Paragraph(val("ucfname"), styles["ucTitle"]))
    sheet.append(Paragraph(f"<b>Código:</b> {val('ucsname')}", styles["Normal"]))
    sheet.append(Paragraph(f"<b>Docente:</b> {val('docente')}", styles["Normal"]))
    sheet.append(Spacer(1, 15))

    # Descrição
    sheet.extend(rounded_framed_block([
        Paragraph("<b>Descrição</b>", styles["Heading2"]),
        Paragraph(val("sinopse"), styles["Normal"])
    ], doc))

    # Avaliação
    estrategia_de_avaliacao = val("estrategia_de_avaliacao")
    tipologia = int(re.search(r"\d+", estrategia_de_avaliacao).group())

    avaliacao_story = [
        Paragraph("<b>Avaliação</b>", styles["Heading2"]),
        Paragraph(estrategia_de_avaliacao, styles["Normal"]),
        Spacer(1, 8)
    ]

    # Tipologia 1
    if tipologia == 1:
        async_box = []
        add_if_value(async_box, "Atividade 01", val("at01_valor"), " valores")
        add_if_value(async_box, "Atividade 02", val("at02_valor"), " valores")
        add_if_value(async_box, "Atividade 03", val("at03_valor"), " valores")
        add_colored_box_to_story(avaliacao_story, async_box, colors.beige)

        sync_box = []
        add_if_value(sync_box, "Atividade 04", val("at04_valor"), " valores")
        add_if_value(sync_box, "fluxo", val("at04_fluxo"))
        add_colored_box_to_story(avaliacao_story, sync_box, colors.lightblue)

        exam_box = []
        add_if_value(exam_box, "Exame na época normal", val("exame"))
        add_colored_box_to_story(avaliacao_story, exam_box, colors.lightgreen)

    # Tipologias 2 e 3
    if tipologia in (2, 3):
        box = []
        add_if_value(box, "Atividade 01", val("at01_valor"), " valores")
        add_if_value(box, "Atividade 02", val("at02_valor"), " valores")
        add_if_value(box, "Atividade 03", val("at03_valor"), " valores")
        add_if_value(box, "Atividade 04", val("at04_valor"), " valores")
        add_colored_box_to_story(avaliacao_story, box, colors.beige)

    # Tipologia 4
    if tipologia == 4:
        async_box = []
        add_if_value(async_box, "Atividade 01", val("at01_valor"), " valores")
        add_colored_box_to_story(avaliacao_story, async_box, colors.beige)

        sync_box = []
        add_if_value(sync_box, "Atividade 02", val("at04_valor"), " valores")
        add_if_value(sync_box, "fluxo", val("at04_fluxo"))
        add_colored_box_to_story(avaliacao_story, sync_box, colors.lightblue)

        exam_box = []
        add_if_value(exam_box, "Exame na época normal", val("exame"))
        add_colored_box_to_story(avaliacao_story, exam_box, colors.lightgreen)

    # fluxo de exame
    if val("exame_fluxo"):
        add_colored_box_to_story(
            avaliacao_story,
            [Paragraph(f"<b>Fluxo de exame: </b> {val('exame_fluxo')}", styles["Normal"])],
            colors.lightgrey
        )

    sheet.extend(rounded_framed_block(avaliacao_story, doc))

    # Outros
    sheet.extend(rounded_framed_block([
        Paragraph("<b>Outros</b>", styles["Heading2"]),
        Paragraph(f"<b>Bibliografia:</b> {val('bibliografia')}", styles["Normal"]),
        Paragraph(f"<b>Dimensão do GATu:</b> {val('dimensao_do_gatu')}", styles["Normal"]),
        Paragraph(f"<b>LIA:</b> {val('lia')}", styles["Normal"]),
    ], doc))

    doc.build(
        sheet,
        onFirstPage=watermark,
        onLaterPages=watermark
    )


# conversão de imagem para PDF
def img_to_pdf(img):
    pil_img = PILImage.open(img)
    pdf_path = str(img.with_stem(img.stem + "_temp").with_suffix('.pdf'))

    width_px, height_px = pil_img.size
    dpi = pil_img.info.get("dpi", (72, 72))[0]

    img_width = (width_px / dpi) * 72
    img_height = (height_px / dpi) * 72

    if width_px > height_px:
        page_size = landscape(A4)

    else:
        page_size = portrait(A4)

    page_width, page_height = page_size

    c = canvas.Canvas(pdf_path, pagesize=page_size)

    scale = min(page_width / img_width, page_height / img_height)

    draw_width = img_width * scale
    draw_height = img_height * scale

    x = (page_width - draw_width) / 2
    y = (page_height - draw_height) / 2

    c.drawImage(img, x, y, draw_width, draw_height)
    c.showPage()
    c.save()

    return pdf_path


# junção de PDFs
def merge_files(pdf_files: Iterable[str | Path], output_path: str | Path) -> None:
    pdf_files = list(pdf_files)

    merger = PdfWriter()

    try:
        for pdf in pdf_files:
            pdf = Path(pdf)

            if pdf.suffix.lower() in ['.png', '.jpg', '.jpeg', '.gif', '.bmp']:
                pdf = img_to_pdf(pdf)

            merger.append(str(pdf))

        with open(output_path, "wb") as f:
            merger.write(f)

    finally:
        merger.close()
