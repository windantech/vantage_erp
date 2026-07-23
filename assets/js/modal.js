$(".emails_msg").click(function () {
  var entry_id = $(this).data("entry_id");
  set_data(entry_id);
});

function set_data(entry_id){
  var data = {
    entry_id: entry_id,
  };

  $.ajax({
    url: "includes/modal/set_data.inc.php",
    type: "post",
    data: data,
    success: function (response) {
      fetch_data();
    },
  });
}

function fetch_data() {
  $.ajax({
    //create an ajax request
    type: "GET",
    url: "includes/modal/fetch_data.inc.php",
    dataType: "html", //expect html to be returned
    success: function (data) {
      $(".modal_show").html(data);
      $("#moreDetailsModal").modal('show')
    },
  });
}

// tasks

$(".newTaskModal").click(function () {
  $("#newTaskModal #p_name_head").html($(this).data("p_name"));
});

$(".viewTaskModal").click(function () {
  $("#viewTaskModal #task_name").html($(this).data("task_name"));
  $("#viewTaskModal #task_status").html($(this).data("task_status"));
  $("#viewTaskModal #task_desc").html($(this).data("task_desc"));
});

// tasks