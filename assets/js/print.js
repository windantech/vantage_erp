$('#print').click(function(){
    var _h = $('head').clone()
    var _p = $('#printable').clone()
    var _d = "<p class='text-center'><b>Project Progress Report as of (" + $('#print_date').val() + ")</b></p>"
    _p.prepend(_d)
    _p.prepend(_h)
    var nw = window.open("","","width=900,height=600")
    nw.document.write(_p.html())
    nw.document.close()
    nw.print()
    setTimeout(function(){
        nw.close()
        end_load()
    },750)
})